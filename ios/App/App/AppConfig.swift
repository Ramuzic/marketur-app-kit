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
        let s: String
        switch name {
        case "home": s = "house.fill"
        case "shop": s = "bag.fill"
        case "cart": s = "cart.fill"
        case "account": s = "person.crop.circle.fill"
        case "search": s = "magnifyingglass"
        case "news": s = "newspaper.fill"
        case "chat": s = "bubble.left.and.bubble.right.fill"
        case "community": s = "person.2.fill"
        case "bell": s = "bell.badge.fill"
        case "heart": s = "heart.fill"
        case "star": s = "star.fill"
        case "map": s = "mappin.circle.fill"
        case "phone": s = "phone.fill"
        case "calendar": s = "calendar"
        case "play": s = "play.circle.fill"
        case "reels": s = "play.rectangle.fill"
        case "grid": s = "square.grid.2x2.fill"
        case "info": s = "info.circle.fill"
        case "menu": s = "line.3.horizontal"
        case "next": s = "safari.fill"
        case "share": s = "square.and.arrow.up.fill"
        case "gift": s = "gift.fill"
        case "tag": s = "tag.fill"
        case "receipt": s = "doc.plaintext.fill"
        case "card": s = "creditcard.fill"
        case "wallet": s = "wallet.pass.fill"
        case "store": s = "building.2.fill"
        case "truck": s = "shippingbox.fill"
        case "ticket": s = "ticket.fill"
        case "bookmark": s = "bookmark.fill"
        case "book": s = "book.fill"
        case "school": s = "graduationcap.fill"
        case "document": s = "doc.text.fill"
        case "mail": s = "envelope.fill"
        case "megaphone": s = "megaphone.fill"
        case "send": s = "paperplane.fill"
        case "link": s = "link"
        case "camera": s = "camera.fill"
        case "photo": s = "photo.fill"
        case "music": s = "music.note"
        case "mic": s = "mic.fill"
        case "video": s = "video.fill"
        case "tv": s = "tv.fill"
        case "game": s = "gamecontroller.fill"
        case "live": s = "dot.radiowaves.left.and.right"
        case "pin": s = "location.fill"
        case "directions": s = "arrow.triangle.turn.up.right.diamond.fill"
        case "car": s = "car.fill"
        case "plane": s = "airplane"
        case "bed": s = "bed.double.fill"
        case "food": s = "fork.knife"
        case "coffee": s = "cup.and.saucer.fill"
        case "cake": s = "gift.circle.fill"
        case "fitness": s = "figure.walk"
        case "health": s = "cross.case.fill"
        case "scissors": s = "scissors"
        case "paw": s = "pawprint.fill"
        case "leaf": s = "leaf.fill"
        case "faith": s = "building.columns.fill"
        case "wrench": s = "wrench.and.screwdriver.fill"
        case "brush": s = "paintbrush.fill"
        case "briefcase": s = "briefcase.fill"
        case "chart": s = "chart.bar.fill"
        case "trend": s = "arrow.up.right"
        case "code": s = "chevron.left.forwardslash.chevron.right"
        case "clock": s = "clock.fill"
        case "trophy": s = "rosette"
        case "fire": s = "flame.fill"
        case "bolt": s = "bolt.fill"
        case "sparkles": s = "sparkles"
        case "globe": s = "globe"
        case "plus": s = "plus.circle.fill"
        case "download": s = "arrow.down.circle.fill"
        case "lock": s = "lock.fill"
        case "shield": s = "checkmark.shield.fill"
        case "settings": s = "gearshape.fill"
        case "help": s = "questionmark.circle.fill"
        case "qr": s = "qrcode.viewfinder"
        case "list": s = "list.bullet"
        default: s = "circle.fill"
        }
        // A name this iPhone's system does not have shows a plain dot, never an empty tab.
        return UIImage(systemName: s) != nil ? s : "circle.fill"
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
