<?php
/**
 * The App Kit assistant (App Kit 1.3.0).
 *
 * A chat box in wp-admin where the site owner asks for changes in plain words ("make the
 * tab bar fixed", "add a Shop tab", "change the homepage headline") and the assistant does
 * them. The AI runs on Marketur with Marketur's key; this site only sends the conversation
 * and runs the tools itself, as the admin who is chatting, through the same checks as any
 * other AI connection (the Marketur MCP plugin's switches, capability checks and log).
 *
 * Tools offered:
 *  - the app tools (app_get_settings, app_update_settings, app_restore_settings, app_check,
 *    app_build, app_build_status), always;
 *  - site_find_tools and site_use_tool, when the Marketur MCP plugin is installed and switched
 *    on: the assistant looks up the website tool it needs by describing the job, then runs it.
 *    That keeps every request small instead of sending hundreds of tool descriptions each time.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Assistant {

    const META  = 'mak_chat';
    const STEPS = 16;

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'wp_ajax_mak_assist', [ __CLASS__, 'ajax' ] );
    }

    public static function menu() {
        add_menu_page( 'Assistant', 'Assistant', 'manage_options', 'mak-assistant', [ __CLASS__, 'page' ], 'dashicons-format-chat', 3 );
    }

    /* ---------------------------------------------------------------- tools */

    private static function mcp_on() {
        return class_exists( 'MMCP_Policy' ) && class_exists( 'MMCP_Tools' ) && MMCP_Policy::server_enabled();
    }

    private static function app_tools() {
        return MAK_AI_Tools::tools( [] );
    }

    /** Tool list for the AI, in the AI's own format. */
    private static function tool_defs() {
        $out = [];
        foreach ( self::app_tools() as $name => $t ) {
            $out[] = [ 'name' => $name, 'description' => $t['description'], 'input_schema' => self::schema( $t['inputSchema'] ) ];
        }
        if ( self::mcp_on() ) {
            $out[] = [
                'name'         => 'site_find_tools',
                'description'  => 'Find the website tools that can do a job, by describing it in a few words (for example "edit a page", "elementor section", "menu items", "seo title", "plugin file", "membership levels", "email broadcast"). Returns up to 8 tools with what they do and the arguments they take. Use site_use_tool to run one.',
                'input_schema' => [ 'type' => 'object', 'properties' => [ 'query' => [ 'type' => 'string' ] ], 'required' => [ 'query' ] ],
            ];
            $out[] = [
                'name'         => 'site_use_tool',
                'description'  => 'Run a website tool found with site_find_tools. Pass its exact name and its arguments. Tools that change things often dry-run first and need confirm=true to apply.',
                'input_schema' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ], 'arguments' => [ 'type' => 'object' ] ], 'required' => [ 'name' ] ],
            ];
        }
        return $out;
    }

    private static function schema( $s ) {
        $s = is_array( $s ) ? $s : [ 'type' => 'object' ];
        if ( empty( $s['properties'] ) || ( is_object( $s['properties'] ) && ! get_object_vars( $s['properties'] ) ) ) $s['properties'] = new stdClass();
        return $s;
    }

    private static function find_tools( $query ) {
        $defs = (array) MMCP_Tools::definitions();
        $words = array_filter( preg_split( '/[^a-z0-9]+/', strtolower( (string) $query ) ) );
        $scored = [];
        foreach ( $defs as $d ) {
            if ( empty( $d['name'] ) || 0 === strpos( $d['name'], 'app_' ) ) continue;
            $hay = strtolower( $d['name'] . ' ' . ( $d['description'] ?? '' ) );
            $score = 0;
            foreach ( $words as $w ) {
                if ( strlen( $w ) < 3 ) continue;
                if ( strpos( strtolower( $d['name'] ), $w ) !== false ) $score += 3;
                elseif ( strpos( $hay, $w ) !== false ) $score += 1;
            }
            if ( $score > 0 ) $scored[] = [ $score, $d ];
        }
        usort( $scored, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
        $out = [];
        foreach ( array_slice( $scored, 0, 8 ) as $x ) {
            $d = $x[1];
            $out[] = [ 'name' => $d['name'], 'description' => mb_substr( (string) ( $d['description'] ?? '' ), 0, 900 ), 'arguments' => $d['inputSchema'] ?? new stdClass() ];
        }
        return $out ?: [ 'none' => 'No tool matched. Try other words, or the tool group may be switched off under Settings > Marketur MCP.' ];
    }

    /** Runs one tool as the admin who is chatting. */
    private static function run( $name, $input ) {
        $input = is_array( $input ) ? $input : [];
        if ( $name === 'site_find_tools' ) return self::mcp_on() ? self::find_tools( $input['query'] ?? '' ) : new WP_Error( 'no_mcp', 'Website tools need the Marketur MCP plugin switched on.' );
        if ( $name === 'site_use_tool' ) {
            if ( ! self::mcp_on() ) return new WP_Error( 'no_mcp', 'Website tools need the Marketur MCP plugin switched on.' );
            $tool = sanitize_key( (string) ( $input['name'] ?? '' ) );
            $args = is_array( $input['arguments'] ?? null ) ? $input['arguments'] : [];
            return MMCP_Policy::execute( $tool, $args, MMCP_Policy::CTX_INTERNAL );
        }
        $app = self::app_tools();
        if ( ! isset( $app[ $name ] ) ) return new WP_Error( 'unknown', 'There is no tool called ' . $name . '.' );
        // Through the MCP plugin when it is on, so its switches and log apply; directly otherwise.
        if ( self::mcp_on() ) return MMCP_Policy::execute( $name, $input, MMCP_Policy::CTX_INTERNAL );
        return call_user_func( $app[ $name ]['callback'], $input );
    }

    /* ---------------------------------------------------------------- conversation */

    private static function history() {
        $h = get_user_meta( get_current_user_id(), self::META, true );
        return is_array( $h ) ? $h : [];
    }

    private static function save( $h ) {
        // Keep it from growing forever: drop the oldest turns, starting the kept part on a typed message.
        if ( count( $h ) > 60 ) {
            $h = array_slice( $h, -60 );
            while ( $h && ! ( $h[0]['role'] === 'user' && is_string( $h[0]['content'] ) ) ) array_shift( $h );
        }
        update_user_meta( get_current_user_id(), self::META, $h );
    }

    private static function hub( $method, $path, $body = null ) {
        $key = (string) Marketur_App_Kit::get( 'hub_key' );
        if ( $key === '' ) return new WP_Error( 'no_key', 'Connect this site to Marketur first: paste your App Kit key under Settings > App Kit.' );
        $url = trailingslashit( (string) apply_filters( 'mak_hub_url', 'https://marketur.net' ) ) . 'wp-json/mak-hub/v1/' . $path;
        $args = [ 'timeout' => 100, 'headers' => [ 'X-MAK-Key' => $key, 'X-MAK-Site' => home_url( '/' ), 'Content-Type' => 'application/json' ] ];
        if ( $method === 'POST' ) { $args['body'] = wp_json_encode( $body ); $r = wp_remote_post( $url, $args ); }
        else $r = wp_remote_get( $url, $args );
        if ( is_wp_error( $r ) ) return new WP_Error( 'hub_down', 'Could not reach Marketur. Try again in a moment.' );
        $j = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( (int) wp_remote_retrieve_response_code( $r ) >= 400 ) return new WP_Error( $j['code'] ?? 'hub', $j['message'] ?? 'Marketur could not answer.' );
        return is_array( $j ) ? $j : [];
    }

    /**
     * One step: send the conversation, get the answer, run any tools it asked for.
     * The browser calls again until done, so no single request runs long.
     */
    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Only site admins can use the assistant.' ], 403 );
        check_ajax_referer( 'mak_assist', 'nonce' );
        @set_time_limit( 120 );
        $h = self::history();

        if ( ! empty( $_POST['reset'] ) ) { delete_user_meta( get_current_user_id(), self::META ); wp_send_json_success( [ 'done' => true, 'text' => '' ] ); }

        $msg = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
        if ( $msg !== '' ) {
            // A new message after an unfinished tool round would break the conversation, so close it first.
            $last = end( $h );
            if ( $last && $last['role'] === 'assistant' ) {
                $open = array_filter( (array) $last['content'], function ( $b ) { return is_array( $b ) && ( $b['type'] ?? '' ) === 'tool_use'; } );
                if ( $open ) $h[] = [ 'role' => 'user', 'content' => array_values( array_map( function ( $b ) { return [ 'type' => 'tool_result', 'tool_use_id' => $b['id'], 'content' => 'Stopped by the user.', 'is_error' => true ]; }, $open ) ) ];
            }
            if ( $h && end( $h )['role'] === 'user' && is_array( end( $h )['content'] ) ) {
                // tool results are pending a reply: add the typed text to the same turn
                $k = count( $h ) - 1;
                $h[ $k ]['content'][] = [ 'type' => 'text', 'text' => $msg ];
            } else {
                $h[] = [ 'role' => 'user', 'content' => $msg ];
            }
        }
        if ( ! $h || end( $h )['role'] !== 'user' ) wp_send_json_success( [ 'done' => true, 'text' => '' ] );

        $r = self::hub( 'POST', 'assistant', [ 'messages' => $h, 'tools' => self::tool_defs() ] );
        if ( is_wp_error( $r ) ) { self::save( $h ); wp_send_json_error( [ 'message' => $r->get_error_message() ] ); }

        $content = (array) ( $r['content'] ?? [] );
        $h[] = [ 'role' => 'assistant', 'content' => $content ];
        $text = []; $did = []; $results = [];
        foreach ( $content as $b ) {
            if ( ( $b['type'] ?? '' ) === 'text' && trim( (string) $b['text'] ) !== '' ) $text[] = $b['text'];
            if ( ( $b['type'] ?? '' ) === 'tool_use' ) {
                $name = (string) $b['name'];
                $res = self::run( $name, $b['input'] ?? [] );
                $err = is_wp_error( $res );
                $out = $err ? $res->get_error_message() : wp_json_encode( $res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
                if ( strlen( (string) $out ) > 24000 ) $out = substr( (string) $out, 0, 24000 ) . ' ...(cut, ask for less)';
                $results[] = [ 'type' => 'tool_result', 'tool_use_id' => $b['id'], 'content' => (string) $out, 'is_error' => $err ];
                $label = $name === 'site_use_tool' ? sanitize_key( (string) ( $b['input']['name'] ?? '' ) ) : $name;
                $did[] = $label . ( $err ? ' (did not work)' : '' );
            }
        }
        $done = ! $results;
        if ( $results ) $h[] = [ 'role' => 'user', 'content' => $results ];
        self::save( $h );
        wp_send_json_success( [ 'done' => $done, 'text' => implode( "\n\n", $text ), 'did' => $did, 'left' => $r['left'] ?? null ] );
    }

    /* ---------------------------------------------------------------- page */

    public static function page() {
        $past = [];
        foreach ( self::history() as $m ) {
            if ( $m['role'] === 'user' && is_string( $m['content'] ) ) $past[] = [ 'who' => 'you', 'text' => $m['content'] ];
            if ( $m['role'] === 'assistant' ) {
                $t = [];
                foreach ( (array) $m['content'] as $b ) if ( ( $b['type'] ?? '' ) === 'text' ) $t[] = $b['text'];
                if ( $t ) $past[] = [ 'who' => 'ai', 'text' => implode( "\n\n", $t ) ];
            }
        }
        $connected = (bool) Marketur_App_Kit::get( 'hub_key' );
        ?>
<div class="wrap mak-as">
  <h1>Assistant</h1>
  <p class="description">Ask for changes to your app or your website in plain words. It reads first, makes the change, and tells you what it did. <?php echo self::mcp_on() ? 'It can work on your whole website through the Marketur MCP plugin.' : 'Right now it can change your app. Switch on the Marketur MCP plugin and it can work on your whole website too.'; ?></p>
  <?php if ( ! $connected ) : ?><div class="notice notice-warning inline"><p>Paste your App Kit key under <a href="<?php echo esc_url( admin_url( 'options-general.php?page=marketur-app-kit' ) ); ?>">Settings &gt; App Kit</a> to switch the assistant on.</p></div><?php endif; ?>
  <div id="mak-log"></div>
  <form id="mak-form">
    <textarea id="mak-in" rows="3" placeholder="For example: make the bottom bar fixed and add a Shop tab"></textarea>
    <div class="mak-row"><button class="button button-primary" id="mak-send" type="submit">Send</button> <a href="#" id="mak-new">New conversation</a> <span id="mak-left"></span></div>
  </form>
</div>
<style>
.mak-as #mak-log{max-width:820px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px;min-height:220px;max-height:62vh;overflow:auto;margin:14px 0}
.mak-as .m{margin:0 0 14px;line-height:1.55;white-space:pre-wrap}
.mak-as .m.you{background:#f0f6fc;border-radius:10px;padding:10px 12px;margin-left:60px}
.mak-as .m.ai{padding:2px 2px}
.mak-as .m.did{font-size:12px;color:#646970;margin:-6px 0 12px}
.mak-as .m.err{color:#b32d2e}
.mak-as #mak-form{max-width:820px}
.mak-as #mak-in{width:100%;border-radius:10px;padding:10px;font-size:14px}
.mak-as .mak-row{margin-top:8px;display:flex;gap:14px;align-items:center}
.mak-as #mak-left{margin-left:auto;color:#646970;font-size:12px}
</style>
<script>
(function(){
  var log=document.getElementById('mak-log'),inp=document.getElementById('mak-in'),btn=document.getElementById('mak-send');
  var url=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce=<?php echo wp_json_encode( wp_create_nonce( 'mak_assist' ) ); ?>;
  var past=<?php echo wp_json_encode( $past ); ?>;
  function esc(s){return String(s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
  function fmt(s){return esc(s).replace(/[*][*](.+?)[*][*]/g,'<strong>$1</strong>').replace(/`([^`]+)`/g,'<code>$1</code>');}
  function add(cls,text,html){var d=document.createElement('div');d.className='m '+cls;d.innerHTML=html?text:fmt(text);log.appendChild(d);log.scrollTop=log.scrollHeight;return d;}
  past.forEach(function(p){add(p.who,p.text);});
  if(!past.length)add('ai','Hi. What would you like to change on your app or your website?');
  function post(data){var f=new FormData();f.append('action','mak_assist');f.append('nonce',nonce);for(var k in data)f.append(k,data[k]);return fetch(url,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();});}
  function step(data,n,wait){
    return post(data).then(function(j){
      if(wait){wait.remove();}
      if(!j||!j.success){add('err',(j&&j.data&&j.data.message)||'Something went wrong. Try again.');return;}
      var d=j.data;
      if(d.text)add('ai',d.text);
      if(d.did&&d.did.length)add('did','Used: '+d.did.join(', '));
      if(d.left!==null&&d.left!==undefined)document.getElementById('mak-left').textContent='Allowance left this month: $'+Number(d.left).toFixed(2);
      if(!d.done){
        if(n>=<?php echo (int) self::STEPS; ?>){add('ai','I have taken a lot of steps on this. Say "carry on" if you want me to keep going.');return;}
        var w=add('did','Working...');
        return step({},n+1,w);
      }
    }).catch(function(){if(wait)wait.remove();add('err','Lost the connection. Try again.');});
  }
  document.getElementById('mak-form').addEventListener('submit',function(e){
    e.preventDefault();var t=inp.value.trim();if(!t||btn.disabled)return;
    add('you',t);inp.value='';btn.disabled=true;var w=add('did','Thinking...');
    step({message:t},1,w).then(function(){btn.disabled=false;inp.focus();});
  });
  inp.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();document.getElementById('mak-form').requestSubmit();}});
  document.getElementById('mak-new').addEventListener('click',function(e){e.preventDefault();post({reset:1}).then(function(){log.innerHTML='';add('ai','New conversation. What would you like to change?');});});
})();
</script>
        <?php
    }
}

MAK_Assistant::init();
