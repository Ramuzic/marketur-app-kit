<?php
/**
 * The App Kit admin look (App Kit 1.7.0).
 *
 * One clean style for every App Kit screen (Your app, Assistant, Launch, Starter Kit), the
 * tab icons (the same shapes the app draws, so what you pick is what you get), the tab and
 * shortcut editor with an icon picker, and the phone preview on the Your app page.
 */

defined( 'ABSPATH' ) || exit;

final class MAK_Admin_UI {

    const PAGES = [ 'marketur-app-kit', 'mak-assistant', 'mak-launch', 'mak-starter' ];

    public static function init() {
        add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
        add_action( 'admin_head', [ __CLASS__, 'css' ] );
    }

    private static function ours() {
        return is_admin() && isset( $_GET['page'] ) && in_array( sanitize_key( $_GET['page'] ), self::PAGES, true );
    }

    public static function body_class( $c ) {
        return self::ours() ? $c . ' mak-ui' : $c;
    }

    /** name => [ label, path ]. The shapes come from the app itself. */
    public static function icons() {
        return [
            'home' => [ 'Home', 'M10,20v-6h4v6h5v-8h3L12,3 2,12h3v8z' ],
            'shop' => [ 'Shop', 'M18,6h-2c0,-2.21 -1.79,-4 -4,-4S8,3.79 8,6H6c-1.1,0 -2,0.9 -2,2v12c0,1.1 0.9,2 2,2h12c1.1,0 2,-0.9 2,-2V8C20,6.9 19.1,6 18,6zM12,4c1.1,0 2,0.9 2,2h-4C10,4.9 10.9,4 12,4zM18,20H6V8h2v2c0,0.55 0.45,1 1,1s1,-0.45 1,-1V8h4v2c0,0.55 0.45,1 1,1s1,-0.45 1,-1V8h2V20z' ],
            'cart' => [ 'Cart', 'M7,18c-1.1,0 -1.99,0.9 -1.99,2S5.9,22 7,22s2,-0.9 2,-2 -0.9,-2 -2,-2zM1,2v2h2l3.6,7.59 -1.35,2.45c-0.16,0.28 -0.25,0.61 -0.25,0.96 0,1.1 0.9,2 2,2h12v-2L7.42,15c-0.14,0 -0.25,-0.11 -0.25,-0.25l0.03,-0.12 0.9,-1.63h7.45c0.75,0 1.41,-0.41 1.75,-1.03l3.58,-6.49c0.08,-0.14 0.12,-0.31 0.12,-0.48 0,-0.55 -0.45,-1 -1,-1H5.21l-0.94,-2H1zM17,18c-1.1,0 -1.99,0.9 -1.99,2s0.89,2 1.99,2 2,-0.9 2,-2 -0.9,-2 -2,-2z' ],
            'account' => [ 'Account', 'M12,12c2.21,0 4,-1.79 4,-4s-1.79,-4 -4,-4 -4,1.79 -4,4 1.79,4 4,4zM12,14c-2.67,0 -8,1.34 -8,4v2h16v-2c0,-2.66 -5.33,-4 -8,-4z' ],
            'search' => [ 'Search', 'M15.5,14h-0.79l-0.28,-0.27C15.41,12.59 16,11.11 16,9.5 16,5.91 13.09,3 9.5,3S3,5.91 3,9.5 5.91,16 9.5,16c1.61,0 3.09,-0.59 4.23,-1.57l0.27,0.28v0.79l5,4.99L20.49,19l-4.99,-5zM9.5,14C7.01,14 5,11.99 5,9.5S7.01,5 9.5,5 14,7.01 14,9.5 11.99,14 9.5,14z' ],
            'news' => [ 'News', 'M19,3H5C3.9,3 3,3.9 3,5v14c0,1.1 0.9,2 2,2h14c1.1,0 2,-0.9 2,-2V5C21,3.9 20.1,3 19,3zM14,17H7v-2h7V17zM17,13H7v-2h10V13zM17,9H7V7h10V9z' ],
            'chat' => [ 'Chat', 'M21,6h-2v9H6v2c0,0.55 0.45,1 1,1h11l4,4V7c0,-0.55 -0.45,-1 -1,-1zM17,12V3c0,-0.55 -0.45,-1 -1,-1H3c-0.55,0 -1,0.45 -1,1v14l4,-4h10c0.55,0 1,-0.45 1,-1z' ],
            'community' => [ 'Community', 'M16,11c1.66,0 2.99,-1.34 2.99,-3S17.66,5 16,5c-1.66,0 -3,1.34 -3,3s1.34,3 3,3zM8,11c1.66,0 2.99,-1.34 2.99,-3S9.66,5 8,5C6.34,5 5,6.34 5,8s1.34,3 3,3zM8,13c-2.33,0 -7,1.17 -7,3.5V19h14v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5zM16,13c-0.29,0 -0.62,0.02 -0.97,0.05 1.16,0.84 1.97,1.97 1.97,3.45V19h6v-2.5c0,-2.33 -4.67,-3.5 -7,-3.5z' ],
            'bell' => [ 'Alerts', 'M12,22c1.1,0 2,-0.9 2,-2h-4c0,1.1 0.89,2 2,2zM18,16v-5c0,-3.07 -1.64,-5.64 -4.5,-6.32V4c0,-0.83 -0.67,-1.5 -1.5,-1.5s-1.5,0.67 -1.5,1.5v0.68C7.63,5.36 6,7.92 6,11v5l-2,2v1h16v-1l-2,-2z' ],
            'heart' => [ 'Saved', 'M12,21.35l-1.45,-1.32C5.4,15.36 2,12.28 2,8.5 2,5.42 4.42,3 7.5,3c1.74,0 3.41,0.81 4.5,2.09C13.09,3.81 14.76,3 16.5,3 19.58,3 22,5.42 22,8.5c0,3.78 -3.4,6.86 -8.55,11.54L12,21.35z' ],
            'star' => [ 'Star', 'M12,17.27L18.18,21l-1.64,-7.03L22,9.24l-7.19,-0.61L12,2 9.19,8.63 2,9.24l5.46,4.73L5.82,21z' ],
            'map' => [ 'Map', 'M12,2C8.13,2 5,5.13 5,9c0,5.25 7,13 7,13s7,-7.75 7,-13c0,-3.87 -3.13,-7 -7,-7zM12,11.5c-1.38,0 -2.5,-1.12 -2.5,-2.5s1.12,-2.5 2.5,-2.5 2.5,1.12 2.5,2.5 -1.12,2.5 -2.5,2.5z' ],
            'phone' => [ 'Call', 'M6.62,10.79c1.44,2.83 3.76,5.14 6.59,6.59l2.2,-2.2c0.27,-0.27 0.67,-0.36 1.02,-0.24 1.12,0.37 2.33,0.57 3.57,0.57 0.55,0 1,0.45 1,1V20c0,0.55 -0.45,1 -1,1 -9.39,0 -17,-7.61 -17,-17 0,-0.55 0.45,-1 1,-1h3.5c0.55,0 1,0.45 1,1 0,1.25 0.2,2.45 0.57,3.57 0.11,0.35 0.03,0.74 -0.25,1.02l-2.2,2.2z' ],
            'calendar' => [ 'Calendar', 'M17,12h-5v5h5v-5zM16,1v2H8V1H6v2H5c-1.11,0 -1.99,0.9 -1.99,2L3,19c0,1.1 0.89,2 2,2h14c1.1,0 2,-0.9 2,-2V5c0,-1.1 -0.9,-2 -2,-2h-1V1h-2zM19,19H5V8h14v11z' ],
            'play' => [ 'Play', 'M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM10,16.5v-9l6,4.5 -6,4.5z' ],
            'reels' => [ 'Videos', 'M20,4H4C2.9,4 2,4.9 2,6v12c0,1.1 0.9,2 2,2h16c1.1,0 2,-0.9 2,-2V6C22,4.9 21.1,4 20,4zM9.5,16.5v-9l7,4.5L9.5,16.5z' ],
            'grid' => [ 'Grid', 'M4,8h4V4H4v4zM10,20h4v-4h-4v4zM4,20h4v-4H4v4zM4,14h4v-4H4v4zM10,14h4v-4h-4v4zM16,4v4h4V4h-4zM10,8h4V4h-4v4zM16,14h4v-4h-4v4zM16,20h4v-4h-4v4z' ],
            'info' => [ 'Info', 'M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM13,17h-2v-6h2v6zM13,9h-2V7h2v2z' ],
            'menu' => [ 'Menu', 'M3,18h18v-2H3v2zM3,13h18v-2H3v2zM3,6v2h18V6H3z' ],
            'next' => [ 'Explore', 'M12,10.9c-0.61,0 -1.1,0.49 -1.1,1.1s0.49,1.1 1.1,1.1c0.61,0 1.1,-0.49 1.1,-1.1s-0.49,-1.1 -1.1,-1.1zM12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10,-4.48 10,-10S17.52,2 12,2zM14.19,14.19L6,18l3.81,-8.19L18,6l-3.81,8.19z' ],
            'share' => [ 'Share', 'M16,5l-1.42,1.42 -1.59,-1.59V16h-1.98V4.83L9.42,6.42 8,5l4,-4 4,4zM20,10v11c0,1.1 -0.9,2 -2,2H6c-1.11,0 -2,-0.9 -2,-2V10c0,-1.11 0.89,-2 2,-2h3v2H6v11h12V10h-3V8h3c1.1,0 2,0.89 2,2z' ],
        ];
    }

    public static function svg( $name, $size = 22 ) {
        $i = self::icons();
        $d = $i[ $name ][1] ?? $i['star'][1];
        return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="' . esc_attr( $d ) . '"/></svg>';
    }

    public static function sparkle( $size = 16 ) {
        return '<svg class="mak-sp" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true"><path fill="currentColor" d="M11 2l1.9 5.6L18.5 9.5l-5.6 1.9L11 17l-1.9-5.6L3.5 9.5l5.6-1.9L11 2zm7.5 11l.95 2.55L22 16.5l-2.55.95L18.5 20l-.95-2.55L15 16.5l2.55-.95L18.5 13z"/></svg>';
    }

    /** Pages people can point a tab or shortcut at: path => title. */
    public static function paths() {
        $out = [ '/' => 'Home page' ];
        foreach ( get_pages( [ 'post_status' => 'publish', 'number' => 200, 'sort_column' => 'menu_order,post_title' ] ) as $p ) {
            $path = (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH );
            if ( $path !== '' && ! isset( $out[ $path ] ) ) $out[ $path ] = $p->post_title;
        }
        $blog = (int) get_option( 'page_for_posts' );
        if ( $blog ) $out[ (string) wp_parse_url( get_permalink( $blog ), PHP_URL_PATH ) ] = 'Blog';
        return $out;
    }

    public static function css() {
        if ( ! self::ours() ) return;
        ?>
<style id="mak-ui">
body.mak-ui #wpcontent{background:#f5f6fa}
body.mak-ui .wrap{--mk-ink:#101828;--mk-soft:#475467;--mk-line:#e4e7ec;--mk-blue:#2563eb;--mk-violet:#7c3aed;font-size:14px;color:var(--mk-ink)}
body.mak-ui .wrap h1{font-size:26px;font-weight:700;letter-spacing:-.01em}
body.mak-ui .wrap .button{display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:0 15px;border-radius:10px;border:1px solid #d0d5dd;background:#fff;color:#344054;font-weight:600;font-size:13.5px;line-height:1;box-shadow:0 1px 2px rgba(16,24,40,.05);transition:background .15s,border-color .15s,box-shadow .15s,transform .05s;vertical-align:middle;text-decoration:none}
body.mak-ui .wrap .button:hover{background:#f9fafb;border-color:#98a2b3;color:#101828}
body.mak-ui .wrap .button:active{transform:translateY(1px)}
body.mak-ui .wrap .button:focus{outline:0;box-shadow:0 0 0 4px rgba(37,99,235,.18)}
body.mak-ui .wrap .button-primary{background:var(--mk-blue);border-color:var(--mk-blue);color:#fff}
body.mak-ui .wrap .button-primary:hover{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
body.mak-ui .wrap .button-hero{min-height:46px;padding:0 22px;font-size:15px;border-radius:12px}
body.mak-ui .wrap .button[disabled],body.mak-ui .wrap .button:disabled{opacity:.55;cursor:default}
body.mak-ui .wrap .button .dashicons{margin:0;line-height:1}
body.mak-ui .wrap .mak-auto{background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9}
body.mak-ui .wrap .mak-auto:hover{background:#ede9fe;border-color:#c4b5fd;color:#5b21b6}
body.mak-ui .wrap .mak-auto.big{background:linear-gradient(135deg,var(--mk-violet),var(--mk-blue));border:0;color:#fff;min-height:48px;padding:0 24px;font-size:15px;border-radius:12px;box-shadow:0 6px 18px rgba(79,70,229,.28)}
body.mak-ui .wrap .mak-auto.big:hover{filter:brightness(1.07);color:#fff}
body.mak-ui .wrap .button.is-busy .mak-sp{animation:mak-spin 1s linear infinite}
@keyframes mak-spin{to{transform:rotate(360deg)}}
body.mak-ui .wrap input[type=text],body.mak-ui .wrap input[type=email],body.mak-ui .wrap input[type=url],body.mak-ui .wrap input[type=password],body.mak-ui .wrap input[type=number],body.mak-ui .wrap select,body.mak-ui .wrap textarea{border:1px solid #d0d5dd;border-radius:9px;padding:8px 11px;min-height:40px;box-shadow:0 1px 2px rgba(16,24,40,.04);font-size:14px}
body.mak-ui .wrap select{padding-right:30px}
body.mak-ui .wrap input:focus,body.mak-ui .wrap select:focus,body.mak-ui .wrap textarea:focus{border-color:#93b4f7;box-shadow:0 0 0 4px rgba(37,99,235,.12);outline:0}
body.mak-ui .wrap .notice{border-radius:12px;border-left-width:4px;box-shadow:0 1px 2px rgba(16,24,40,.05)}
body.mak-ui .wrap .description{color:var(--mk-soft)}
</style>
        <?php
    }

    /** The tab / shortcut editor and the phone preview script. Printed once on the Your app page. */
    public static function editor_js() {
        $icons = [];
        foreach ( self::icons() as $k => $v ) $icons[ $k ] = [ $v[0], $v[1] ];
        ?>
<datalist id="mak-paths"><?php foreach ( self::paths() as $p => $t ) echo '<option value="' . esc_attr( $p ) . '">' . esc_html( $t ) . '</option>'; ?></datalist>
<div id="mak-pick" class="mak-pick" hidden><div class="mak-pick-h">Pick an icon</div><div class="mak-pick-g"></div></div>
<script>
(function(){
  var ICONS=<?php echo wp_json_encode( $icons ); ?>;
  function svg(n,s){var i=ICONS[n]||ICONS.star;s=s||20;return '<svg viewBox="0 0 24 24" width="'+s+'" height="'+s+'" aria-hidden="true"><path fill="currentColor" d="'+i[1]+'"/></svg>';}
  window.makSvg=svg;
  var pick=document.getElementById('mak-pick');document.body.appendChild(pick);var grid=pick.querySelector('.mak-pick-g'),pickFor=null;
  Object.keys(ICONS).forEach(function(k){var b=document.createElement('button');b.type='button';b.dataset.icon=k;b.innerHTML=svg(k,22)+'<span>'+ICONS[k][0]+'</span>';grid.appendChild(b);});
  grid.addEventListener('click',function(e){var b=e.target.closest('button');if(!b||!pickFor)return;pickFor(b.dataset.icon);close();});
  function close(){pick.hidden=true;pickFor=null;}
  document.addEventListener('click',function(e){if(!pick.hidden&&!pick.contains(e.target)&&!e.target.closest('.mak-ib'))close();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
  function openPick(btn,cb){var r=btn.getBoundingClientRect();pick.hidden=false;var h=pick.offsetHeight,w=pick.offsetWidth,vw=document.documentElement.clientWidth;var top=r.bottom+6;if(top+h>window.innerHeight&&r.top-h-6>0)top=r.top-h-6;pick.style.top=(window.scrollY+top)+'px';pick.style.left=(window.scrollX+Math.max(8,Math.min(r.left,vw-w-8)))+'px';pickFor=cb;}

  document.querySelectorAll('.mak-rows').forEach(function(box){
    var ta=document.getElementById(box.dataset.target),max=+box.dataset.max||5,noun=box.dataset.noun||'tab',rows=[];
    function parse(){rows=ta.value.split('\n').map(function(l){return l.trim();}).filter(Boolean).map(function(l){var p=l.split('|').map(function(x){return x.trim();});return {label:p[0]||'',path:p[1]||'/',icon:p[2]||'star',extra:p.slice(3).join('|')};});}
    function save(){ta.value=rows.filter(function(r){return r.label!==''||r.path!=='';}).map(function(r){return [r.label,r.path||'/',r.icon||'star'].concat(r.extra?[r.extra]:[]).join('|');}).join('\n');ta.dispatchEvent(new CustomEvent('mak:change',{bubbles:true}));}
    function draw(){
      box.innerHTML='';
      rows.forEach(function(r,i){
        var el=document.createElement('div');el.className='mak-row';
        el.innerHTML='<button type="button" class="mak-ib" title="Change the icon">'+svg(r.icon,22)+'</button>'
          +'<input type="text" class="mak-l" placeholder="Label" maxlength="20">'
          +'<input type="text" class="mak-p" placeholder="/page-address/ or a full link" list="mak-paths">'
          +'<span class="mak-mv"><button type="button" class="mak-up" title="Move up">&#8593;</button><button type="button" class="mak-dn" title="Move down">&#8595;</button></span>'
          +'<button type="button" class="mak-x" title="Remove">&times;</button>';
        el.querySelector('.mak-l').value=r.label;el.querySelector('.mak-p').value=r.path;
        el.querySelector('.mak-l').addEventListener('input',function(e){r.label=e.target.value;save();});
        el.querySelector('.mak-p').addEventListener('input',function(e){r.path=e.target.value;save();});
        el.querySelector('.mak-ib').addEventListener('click',function(e){var b=e.currentTarget;openPick(b,function(n){r.icon=n;b.innerHTML=svg(n,22);save();});});
        el.querySelector('.mak-up').addEventListener('click',function(){if(i>0){rows.splice(i-1,0,rows.splice(i,1)[0]);save();draw();}});
        el.querySelector('.mak-dn').addEventListener('click',function(){if(i<rows.length-1){rows.splice(i+1,0,rows.splice(i,1)[0]);save();draw();}});
        el.querySelector('.mak-x').addEventListener('click',function(){rows.splice(i,1);save();draw();});
        box.appendChild(el);
      });
      if(rows.length<max){var a=document.createElement('button');a.type='button';a.className='button mak-add';a.textContent='+ Add a '+noun;a.addEventListener('click',function(){rows.push({label:'',path:'',icon:'star',extra:''});draw();var ls=box.querySelectorAll('.mak-l');ls[ls.length-1].focus();});box.appendChild(a);}
      else{var n=document.createElement('p');n.className='description';n.textContent='That is the most a '+noun+' bar can hold ('+max+').';box.appendChild(n);}
    }
    ta.addEventListener('input',function(){parse();draw();ta.dispatchEvent(new CustomEvent('mak:change',{bubbles:true}));});
    parse();draw();
  });

  /* Phone preview */
  var ph=document.querySelector('.mak-phone');if(!ph)return;
  var bar=ph.querySelector('.mak-pbar'),top=ph.querySelector('.mak-ptop'),form=document.getElementById('mak-form');
  function col(k){var i=form.querySelector('input[name="mak[colors]['+k+']"]');return i?i.value:'#000';}
  function paint(){
    var tabs=(document.getElementById('mak_tabs').value||'').split('\n').map(function(l){return l.trim();}).filter(Boolean).slice(0,5).map(function(l){var p=l.split('|');return {label:(p[0]||'').trim(),icon:(p[2]||'home').trim()};});
    var nav=(form.querySelector('[name="mak[nav]"]')||{}).value||'floating';
    ph.dataset.nav=nav;top.style.background=col('top');
    bar.style.background=col('bar');
    bar.innerHTML=tabs.map(function(t,i){return '<span style="color:'+(i===0?col('active'):col('inactive'))+'">'+svg(t.icon,20)+'<b>'+t.label.replace(/[<>&]/g,'')+'</b></span>';}).join('');
    var n=ph.querySelector('.mak-pname');if(n)n.textContent=(form.querySelector('[name="mak[app_name]"]')||{}).value||'';
  }
  form.addEventListener('input',paint);form.addEventListener('change',paint);document.addEventListener('mak:change',paint);paint();
})();
</script>
        <?php
    }
}

MAK_Admin_UI::init();
