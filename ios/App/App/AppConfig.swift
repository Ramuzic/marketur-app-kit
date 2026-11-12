import UIKit

/// Everything the native shell needs to know, read from www/app.json
/// (copied into the app as public/app.json by `npx cap copy`).
/// Edit app.json to change tabs, colors or onboarding text without touching code.
struct AppTab {
    let label: String
    let url: URL
    let icon: String
    let action: String
    /// Extra page paths that also light this tab (Home lights on the site root too).
    let match: [String]
}

struct OnboardingPage {
    let icon: String
    let title: String
    let text: String
    let push: Bool
}

final class AppConfig {
    static let shared = AppConfig()

    let start: URL
    let host: String
    let oneSignalAppId: String
    let tabs: [AppTab]
    let onboarding: [OnboardingPage]
    let topColor: UIColor
    let barColor: UIColor
    let activeColor: UIColor
    let inactiveColor: UIColor
    let paperColor: UIColor
    let inkColor: UIColor
    let mutedColor: UIColor
    let offlineTitle: String
    let offlineText: String
    let offlineButton: String
    /// Bottom bar style: floating (the glass pill on iOS 26), fixed (a full width bar) or none.
    let nav: String
    /// Where the live settings come from (the App Kit plugin on the site).
    let configURL: URL?

    /// Live settings saved from the site, used the next time the app opens.
    static var cacheURL: URL {
        let dir = FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
        try? FileManager.default.createDirectory(at: dir, withIntermediateDirectories: true)
        return dir.appendingPathComponent("app-config.json")
    }

    private init() {
        var json: [String: Any] = [:]
        if let url = Bundle.main.url(forResource: "app", withExtension: "json", subdirectory: "public"),
           let data = try? Data(contentsOf: url),
           let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
            json = obj
        }
        let siteHost = json["host"] as? String ?? "example.com"
        host = siteHost
        let h = siteHost.lowercased()
        func own(_ u: URL?) -> Bool {
            guard let x = u?.host?.lowercased() else { return false }
            return x == h || x.hasSuffix("." + h)
        }
        configURL = URL(string: json["config_url"] as? String ?? "https://\(siteHost)/wp-json/mak/v1/app-config")
        // Only these keys can change without a new build, and only for this site.
        if let data = try? Data(contentsOf: AppConfig.cacheURL),
           let remote = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
           (remote["host"] as? String ?? siteHost).lowercased() == h {
            for key in ["start", "nav", "tabs", "colors", "onboarding", "offline"] {
                if let v = remote[key] { json[key] = v }
            }
        }
        let fallbackStart = URL(string: "https://\(siteHost)/")!
        let wantStart = URL(string: json["start"] as? String ?? "")
        start = own(wantStart) ? wantStart! : fallbackStart
        nav = json["nav"] as? String ?? "floating"
        oneSignalAppId = json["onesignal_app_id"] as? String ?? ""

        let colors = json["colors"] as? [String: String] ?? [:]
        topColor = UIColor(hex: colors["top"] ?? "#F4F0E8")
        barColor = UIColor(hex: colors["bar"] ?? "#121212")
        activeColor = UIColor(hex: colors["active"] ?? "#FF5A1F")
        inactiveColor = UIColor(hex: colors["inactive"] ?? "#B9B2A6")
        paperColor = UIColor(hex: colors["paper"] ?? "#F4F0E8")
        inkColor = UIColor(hex: colors["ink"] ?? "#121212")
        mutedColor = UIColor(hex: colors["muted"] ?? "#6B665E")

        var t: [AppTab] = []
        for item in json["tabs"] as? [[String: Any]] ?? [] {
            guard let label = item["label"] as? String,
                  let u = URL(string: item["url"] as? String ?? ""), own(u), t.count < 5 else { continue }
            t.append(AppTab(label: label, url: u, icon: item["icon"] as? String ?? "home", action: item["action"] as? String ?? "navigate", match: item["match"] as? [String] ?? []))
        }
        if t.isEmpty {
            t = [AppTab(label: "Home", url: start, icon: "home", action: "navigate", match: ["/"])]
        }
        tabs = t

        var o: [OnboardingPage] = []
        for item in json["onboarding"] as? [[String: Any]] ?? [] {
            o.append(OnboardingPage(icon: item["icon"] as? String ?? "next",
                                    title: item["title"] as? String ?? "",
                                    text: item["text"] as? String ?? "",
                                    push: item["push"] as? Bool ?? false))
        }
        onboarding = o

        let off = json["offline"] as? [String: String] ?? [:]
        offlineTitle = off["title"] ?? "You're offline"
        offlineText = off["text"] ?? "Check your connection and try again."
        offlineButton = off["button"] ?? "Try again"
    }

    /// Fetches the live settings from the site in the background and keeps them for the
    /// next time the app opens. A bad answer is ignored, so the app never breaks on it.
    static func refresh() {
        let cfg = AppConfig.shared
        guard let url = cfg.configURL, url.scheme == "https", cfg.isOwn(url) else { return }
        var req = URLRequest(url: url, cachePolicy: .reloadIgnoringLocalCacheData, timeoutInterval: 8)
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        URLSession.shared.dataTask(with: req) { data, resp, _ in
            guard let data = data, data.count < 200_000,
                  (resp as? HTTPURLResponse)?.statusCode == 200,
                  let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  (obj["host"] as? String ?? "").lowercased() == cfg.host.lowercased() else { return }
            try? data.write(to: AppConfig.cacheURL, options: .atomic)
        }.resume()
    }

    /// SF Symbol for each icon name used in app.json.
    static func symbol(_ name: String) -> String {
        switch name {
        case "home": return "house.fill"
        case "next": return "safari.fill"
        case "reels": return "play.rectangle.fill"
        case "community": return "person.2.fill"
        case "chat": return "bubble.left.and.bubble.right.fill"
        case "share": return "square.and.arrow.up.fill"
        case "bell": return "bell.badge.fill"
        case "shop": return "bag.fill"
        case "cart": return "cart.fill"
        case "account": return "person.crop.circle.fill"
        case "search": return "magnifyingglass"
        case "heart": return "heart.fill"
        case "star": return "star.fill"
        case "map": return "mappin.circle.fill"
        case "phone": return "phone.fill"
        case "menu": return "line.3.horizontal"
        case "calendar": return "calendar"
        case "news": return "newspaper.fill"
        case "play": return "play.circle.fill"
        case "grid": return "square.grid.2x2.fill"
        case "info": return "info.circle.fill"
        default: return "circle.fill"
        }
    }

    /// True when a link belongs to the site and should stay inside the app.
    func isOwn(_ url: URL?) -> Bool {
        guard let h = url?.host?.lowercased() else { return false }
        return h == host || h.hasSuffix("." + host)
    }
}

extension UIColor {
    convenience init(hex: String) {
        var s = hex.trimmingCharacters(in: .whitespacesAndNewlines)
        if s.hasPrefix("#") { s.removeFirst() }
        var v: UInt64 = 0
        Scanner(string: s).scanHexInt64(&v)
        if s.count == 6 {
            self.init(red: CGFloat((v >> 16) & 0xFF) / 255, green: CGFloat((v >> 8) & 0xFF) / 255, blue: CGFloat(v & 0xFF) / 255, alpha: 1)
        } else {
            self.init(white: 0, alpha: 1)
        }
    }
}
