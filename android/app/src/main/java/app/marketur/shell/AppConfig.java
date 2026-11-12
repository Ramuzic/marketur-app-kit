package app.marketur.shell;

import android.content.Context;
import android.graphics.Color;
import android.net.Uri;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import org.json.JSONArray;
import org.json.JSONObject;

/**
 * Everything the native shell needs, read from www/app.json (copied into the
 * app as assets/public/app.json by "npx cap copy"). Edit app.json to change
 * tabs, colors or onboarding text without touching code.
 */
public final class AppConfig {

    public static final class Tab {
        public final String label, url, icon, action;
        /** Extra page paths that also light this tab (Home lights on the site root too). */
        public final List<String> match;
        Tab(String label, String url, String icon, String action, List<String> match) {
            this.label = label; this.url = url; this.icon = icon; this.action = action; this.match = match;
        }
    }

    public static final class Page {
        public final String icon, title, text;
        public final boolean push;
        Page(String icon, String title, String text, boolean push) {
            this.icon = icon; this.title = title; this.text = text; this.push = push;
        }
    }

    private static AppConfig instance;

    public String start = "https://example.com/";
    public String host = "example.com";
    public String oneSignalAppId = "";
    public int top = Color.parseColor("#F4F0E8");
    public int bar = Color.parseColor("#121212");
    public int active = Color.parseColor("#FF5A1F");
    public int inactive = Color.parseColor("#B9B2A6");
    public int paper = Color.parseColor("#F4F0E8");
    public int ink = Color.parseColor("#121212");
    public int muted = Color.parseColor("#6B665E");
    public String offlineTitle = "You're offline";
    public String offlineText = "Check your connection and try again.";
    public String offlineButton = "Try again";
    /** Bottom bar style: floating (a rounded bar with space around it), fixed (full width) or none. */
    public String nav = "floating";
    /** Where the live settings come from (the App Kit plugin on the site). */
    public String configUrl = "";
    public final List<Tab> tabs = new ArrayList<>();
    public final List<Page> onboarding = new ArrayList<>();

    public static synchronized AppConfig get(Context c) {
        if (instance == null) instance = new AppConfig(c.getApplicationContext());
        return instance;
    }

    private AppConfig(Context c) {
        try (InputStream in = c.getAssets().open("public/app.json")) {
            java.io.ByteArrayOutputStream out = new java.io.ByteArrayOutputStream();
            byte[] buf = new byte[4096];
            int n;
            while ((n = in.read(buf)) != -1) out.write(buf, 0, n);
            JSONObject j = new JSONObject(new String(out.toByteArray(), StandardCharsets.UTF_8));
            host = j.optString("host", host);
            configUrl = j.optString("config_url", "https://" + host + "/wp-json/mak/v1/app-config");
            // Live settings saved from the site last time (tabs, colors, bar style, onboarding).
            // Only these keys can change without a new build, and only for this site.
            try {
                JSONObject r = new JSONObject(readFile(new File(c.getFilesDir(), CACHE)));
                if (host.equalsIgnoreCase(r.optString("host", host))) {
                    for (String k : new String[]{"start", "nav", "tabs", "colors", "onboarding", "offline"}) {
                        if (r.has(k)) j.put(k, r.get(k));
                    }
                }
            } catch (Exception ignored) {}
            start = j.optString("start", start);
            if (!isOwn(start)) start = "https://" + host + "/";
            nav = j.optString("nav", nav);
            oneSignalAppId = j.optString("onesignal_app_id", "");
            JSONObject col = j.optJSONObject("colors");
            if (col != null) {
                top = color(col, "top", top);
                bar = color(col, "bar", bar);
                active = color(col, "active", active);
                inactive = color(col, "inactive", inactive);
                paper = color(col, "paper", paper);
                ink = color(col, "ink", ink);
                muted = color(col, "muted", muted);
            }
            JSONArray t = j.optJSONArray("tabs");
            if (t != null) {
                for (int i = 0; i < t.length(); i++) {
                    JSONObject o = t.getJSONObject(i);
                    List<String> m = new ArrayList<>();
                    JSONArray ma = o.optJSONArray("match");
                    if (ma != null) for (int k = 0; k < ma.length(); k++) m.add(ma.optString(k));
                    String u = o.optString("url", start);
                    if (!isOwn(u) || tabs.size() >= 5) continue;
                    tabs.add(new Tab(o.optString("label"), u, o.optString("icon", "home"), o.optString("action", "navigate"), m));
                }
            }
            JSONArray ob = j.optJSONArray("onboarding");
            if (ob != null) {
                for (int i = 0; i < ob.length(); i++) {
                    JSONObject o = ob.getJSONObject(i);
                    onboarding.add(new Page(o.optString("icon", "next"), o.optString("title"), o.optString("text"), o.optBoolean("push", false)));
                }
            }
            JSONObject off = j.optJSONObject("offline");
            if (off != null) {
                offlineTitle = off.optString("title", offlineTitle);
                offlineText = off.optString("text", offlineText);
                offlineButton = off.optString("button", offlineButton);
            }
        } catch (Exception ignored) {
            // Fall back to the defaults above.
        }
        if (tabs.isEmpty()) {
            List<String> m = new ArrayList<>();
            m.add("/");
            tabs.add(new Tab("Home", start, "home", "navigate", m));
        }
    }

    private static final String CACHE = "app-config.json";

    private static String readFile(File f) throws Exception {
        try (InputStream in = new FileInputStream(f)) {
            java.io.ByteArrayOutputStream out = new java.io.ByteArrayOutputStream();
            byte[] buf = new byte[4096];
            int n;
            while ((n = in.read(buf)) != -1) out.write(buf, 0, n);
            return new String(out.toByteArray(), StandardCharsets.UTF_8);
        }
    }

    /**
     * Fetches the live settings from the site in the background and keeps them for the
     * next time the app opens. A bad answer is ignored, so the app never breaks on it.
     */
    public static void refresh(Context c) {
        final Context app = c.getApplicationContext();
        final AppConfig cfg = get(app);
        if (cfg.configUrl == null || !cfg.configUrl.startsWith("https://") || !cfg.isOwn(cfg.configUrl)) return;
        new Thread(() -> {
            HttpURLConnection con = null;
            try {
                con = (HttpURLConnection) new URL(cfg.configUrl).openConnection();
                con.setConnectTimeout(8000);
                con.setReadTimeout(8000);
                con.setRequestProperty("Accept", "application/json");
                if (con.getResponseCode() != 200) return;
                java.io.ByteArrayOutputStream out = new java.io.ByteArrayOutputStream();
                try (InputStream in = con.getInputStream()) {
                    byte[] buf = new byte[4096];
                    int n;
                    while ((n = in.read(buf)) != -1 && out.size() < 200000) out.write(buf, 0, n);
                }
                String body = new String(out.toByteArray(), StandardCharsets.UTF_8);
                JSONObject r = new JSONObject(body);
                if (!cfg.host.equalsIgnoreCase(r.optString("host", ""))) return;
                File tmp = new File(app.getFilesDir(), CACHE + ".tmp");
                try (FileOutputStream f = new FileOutputStream(tmp)) { f.write(body.getBytes(StandardCharsets.UTF_8)); }
                tmp.renameTo(new File(app.getFilesDir(), CACHE));
            } catch (Exception ignored) {
            } finally {
                if (con != null) con.disconnect();
            }
        }).start();
    }

    private static int color(JSONObject o, String key, int def) {
        try { return Color.parseColor(o.optString(key)); } catch (Exception e) { return def; }
    }

    /** Vector icon for each icon name used in app.json. */
    public static int icon(String name) {
        switch (name) {
            case "home": return R.drawable.ic_tab_home;
            case "next": return R.drawable.ic_tab_next;
            case "reels": return R.drawable.ic_tab_reels;
            case "community": return R.drawable.ic_tab_community;
            case "chat": return R.drawable.ic_tab_chat;
            case "share": return R.drawable.ic_tab_share;
            case "bell": return R.drawable.ic_tab_bell;
            case "shop": return R.drawable.ic_tab_shop;
            case "cart": return R.drawable.ic_tab_cart;
            case "account": return R.drawable.ic_tab_account;
            case "search": return R.drawable.ic_tab_search;
            case "heart": return R.drawable.ic_tab_heart;
            case "star": return R.drawable.ic_tab_star;
            case "map": return R.drawable.ic_tab_map;
            case "phone": return R.drawable.ic_tab_phone;
            case "menu": return R.drawable.ic_tab_menu;
            case "calendar": return R.drawable.ic_tab_calendar;
            case "news": return R.drawable.ic_tab_news;
            case "play": return R.drawable.ic_tab_play;
            case "grid": return R.drawable.ic_tab_grid;
            case "info": return R.drawable.ic_tab_info;
            default: return R.drawable.ic_tab_home;
        }
    }

    /** True when a link belongs to the site and should stay inside the app. */
    public boolean isOwn(String url) {
        try {
            String h = Uri.parse(url).getHost();
            if (h == null) return false;
            h = h.toLowerCase();
            return h.equals(host) || h.endsWith("." + host);
        } catch (Exception e) {
            return false;
        }
    }
}
