import UIKit
import WebKit
import Network

extension Notification.Name {
    /// Posted (with a URL as object) when a notification is tapped.
    static let nexAppOpenURL = Notification.Name("nexAppOpenURL")
}

/// The app's main screen: the site in a web view, a native tab bar under it,
/// a native offline screen, pull to refresh and the first-launch onboarding.
class RootViewController: UIViewController, UITabBarDelegate {

    /// A notification tapped before this screen existed (cold start) waits here.
    static var pendingURL: URL?

    private let config = AppConfig.shared
    private let bridgeVC = AppBridgeViewController()
    private let tabBar = UITabBar()
    /// The fixed full width bar on iOS 26 (Apple's own bar there is only the floating pill).
    private let kitBar = KitBar()
    private var useKit: Bool {
        if #available(iOS 26.0, *) { return config.nav == "fixed" }
        return false
    }
    /// Whichever bar this app uses.
    private var bar: UIView { useKit ? kitBar : tabBar }
    private let offlineView = UIView()
    private let refresh = UIRefreshControl()
    private let monitor = NWPathMonitor()
    private var observers: [NSKeyValueObservation] = []
    private var lastTag = 0
    private var pendingChat = false
    private var isOffline = false
    private var shownOnboarding = false
    private var tabHidden = false
    /// The tabs stay out of sight until the first page has loaded and the splash screen has gone.
    private var booting = true
    private var wantHidden = false
    private var launchedAt = Date()
    private var webToTab: NSLayoutConstraint!
    private var webToBottom: NSLayoutConstraint!
    /// How much of the screen the keyboard covers (0 when it is down).
    private var keyboardHeight: CGFloat = 0

    /// iOS 26 draws the tab bar as a floating glass pill, so the page runs underneath it.
    private var floating: Bool {
        if config.nav != "floating" { return false }
        if #available(iOS 26.0, *) { return true }
        return false
    }

    private let chatJS = "(function(){try{if(window.MobileApp&&typeof MobileApp.open==='function'){MobileApp.open();return 'ok';}if(window.Widget&&typeof Widget.open==='function'){Widget.open();return 'ok';}var b=document.getElementById('bmc-mobile-btn')||document.getElementById('bmc-pill');if(b){b.click();return 'ok';}}catch(e){}return 'no';})()"

    override var preferredStatusBarStyle: UIStatusBarStyle { .darkContent }
    override var supportedInterfaceOrientations: UIInterfaceOrientationMask { .portrait }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = config.topColor

        // The site.
        addChild(bridgeVC)
        let web = bridgeVC.view!
        web.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(web)
        bridgeVC.didMove(toParent: self)

        // The native tab bar.
        buildTabBar()
        view.addSubview(bar)
        bar.translatesAutoresizingMaskIntoConstraints = false

        webToTab = web.bottomAnchor.constraint(equalTo: bar.topAnchor)
        webToBottom = web.bottomAnchor.constraint(equalTo: view.bottomAnchor)
        (floating ? webToBottom : webToTab).isActive = true
        NSLayoutConstraint.activate([
            web.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            web.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            web.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            bar.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            bar.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            bar.bottomAnchor.constraint(equalTo: view.bottomAnchor),
            bar.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -49)
        ])

        // Hidden while the splash screen shows; finishBoot() brings it in.
        launchedAt = Date()
        applyTabBar(true, animated: false)
        // Never leave the tabs away if the first page is slow or fails.
        DispatchQueue.main.asyncAfter(deadline: .now() + 6) { [weak self] in self?.finishBoot() }

        buildOfflineView()
        watchWebView()
        watchNetwork()

        NotificationCenter.default.addObserver(forName: .nexAppOpenURL, object: nil, queue: .main) { [weak self] note in
            if let u = note.object as? URL { RootViewController.pendingURL = nil; self?.open(u) }
        }

        // The keyboard: the page shrinks to fit above it, like a native screen,
        // instead of iOS sliding the whole page up and leaving pop-ups out of place.
        for name in [UIResponder.keyboardWillChangeFrameNotification, UIResponder.keyboardWillHideNotification] {
            NotificationCenter.default.addObserver(forName: name, object: nil, queue: .main) { [weak self] note in
                self?.keyboardChanged(note)
            }
        }
    }

    private func keyboardChanged(_ note: Notification) {
        let info = note.userInfo ?? [:]
        var cover: CGFloat = 0
        if note.name != UIResponder.keyboardWillHideNotification,
           let end = (info[UIResponder.keyboardFrameEndUserInfoKey] as? NSValue)?.cgRectValue {
            let f = view.convert(end, from: nil)
            cover = max(0, view.bounds.maxY - f.minY)
        }
        if cover < 80 { cover = 0 } // a hardware keyboard's small bar is not a keyboard
        if cover == keyboardHeight { return }
        let wasUp = keyboardHeight > 0
        keyboardHeight = cover
        let duration = (info[UIResponder.keyboardAnimationDurationUserInfoKey] as? Double) ?? 0.25
        if cover > 0 && !wasUp {
            applyTabBar(true)
        } else if cover == 0 && !booting {
            applyTabBar(wantHidden)
        }
        layoutWeb()
        UIView.animate(withDuration: duration, delay: 0, options: [.beginFromCurrentState]) {
            self.view.layoutIfNeeded()
        }
    }

    /// Where the page ends: above the keyboard, above the classic tab bar, or at the bottom.
    private func layoutWeb() {
        webToTab.isActive = false
        webToBottom.isActive = false
        if keyboardHeight > 0 {
            webToBottom.constant = -keyboardHeight
            webToBottom.isActive = true
        } else {
            webToBottom.constant = 0
            (floating || tabHidden ? webToBottom : webToTab).isActive = true
        }
    }

    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        updateObscured()
    }

    /// With the floating tab bar, tell the page how much of its bottom is covered,
    /// so fixed buttons and the end of the page sit above the glass pill.
    private func updateObscured() {
        #if compiler(>=6.2)
        if #available(iOS 26.0, *), let web = bridgeVC.webView {
            let inset = tabHidden || !floating ? 0 : max(0, view.bounds.maxY - bar.frame.minY)
            if web.obscuredContentInsets.bottom != inset {
                web.obscuredContentInsets = UIEdgeInsets(top: 0, left: 0, bottom: inset, right: 0)
            }
        }
        #endif
    }

    /// Slides the tab bar away (full screen browsing, scrolling down, pop-ups) and back.
    /// While the app is starting this only remembers the wish; finishBoot() applies it.
    func setTabBarHidden(_ hidden: Bool) {
        wantHidden = hidden
        if booting || keyboardHeight > 0 { return }
        applyTabBar(hidden)
    }

    /// A red count on the tab with this action (the Chat tab shows unread messages). 0 clears it.
    func setTabBadge(action: String, count: Int) {
        if useKit {
            for (i, t) in config.tabs.enumerated() where t.action == action {
                kitBar.setBadge(i, count > 0 ? (count > 99 ? "99+" : String(count)) : nil)
            }
            return
        }
        guard let items = tabBar.items else { return }
        for (i, t) in config.tabs.enumerated() where t.action == action && i < items.count {
            items[i].badgeValue = count > 0 ? (count > 99 ? "99+" : String(count)) : nil
            items[i].badgeColor = UIColor(red: 0.94, green: 0.27, blue: 0.27, alpha: 1)
        }
    }

    /// First page loaded: wait for the splash screen to finish, then fade the tabs in.
    private func finishBoot() {
        if !booting { return }
        let splash = 1.3 // the splash screen shows for 1.2 seconds (capacitor.config.json)
        let wait = max(0, splash - Date().timeIntervalSince(launchedAt))
        DispatchQueue.main.asyncAfter(deadline: .now() + wait) { [weak self] in
            guard let self = self, self.booting else { return }
            self.booting = false
            self.applyTabBar(self.wantHidden)
        }
    }

    private func applyTabBar(_ wantHide: Bool, animated: Bool = true) {
        // The none style never shows the bar.
        let hidden = wantHide || config.nav == "none"
        if hidden == tabHidden && animated { return }
        tabHidden = hidden
        layoutWeb()
        bar.isUserInteractionEnabled = !hidden
        let change = {
            self.bar.alpha = hidden ? 0 : 1
            self.bar.transform = hidden ? CGAffineTransform(translationX: 0, y: max(self.bar.bounds.height, 90)) : .identity
            self.view.layoutIfNeeded()
        }
        if animated {
            UIView.animate(withDuration: 0.25, delay: 0, options: [.curveEaseInOut, .beginFromCurrentState], animations: change)
        } else {
            change()
        }
        updateObscured()
    }

    /// Screenshot mode: -NexShot skips onboarding (unless -NexShowOnboarding),
    /// -NexURL <address> opens that page once the app has started.
    private var shotURLDone = false
    private func screenshotSetup() {
        let args = ProcessInfo.processInfo.arguments
        guard args.contains("-NexShot") else { return }
        if !args.contains("-NexShowOnboarding") { UserDefaults.standard.set(true, forKey: "nexapp_onboarded") }
        if !shotURLDone, let i = args.firstIndex(of: "-NexURL"), i + 1 < args.count, let u = URL(string: args[i + 1]) {
            shotURLDone = true
            DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) { [weak self] in
                self?.bridgeVC.webView?.load(URLRequest(url: u))
            }
        }
    }

    override func viewDidAppear(_ animated: Bool) {
        super.viewDidAppear(animated)
        screenshotSetup()
        if let u = RootViewController.pendingURL {
            RootViewController.pendingURL = nil
            open(u)
        }
        if !shownOnboarding && !UserDefaults.standard.bool(forKey: "nexapp_onboarded") && !config.onboarding.isEmpty {
            shownOnboarding = true
            let ob = OnboardingViewController()
            ob.modalPresentationStyle = .fullScreen
            ob.onDone = { UserDefaults.standard.set(true, forKey: "nexapp_onboarded") }
            present(ob, animated: false)
        }
    }

    // MARK: - Tab bar

    private func buildTabBar() {
        if useKit {
            kitBar.build(tabs: config.tabs, background: config.barColor, active: config.activeColor, inactive: config.inactiveColor)
            kitBar.onSelect = { [weak self] i in self?.selectTab(i) }
            return
        }
        tabBar.delegate = self
        tabBar.tintColor = config.activeColor
        if !floating {
            // Before iOS 26: a solid bar in the site's dark colour.
            classicAppearance()
        } else {
            // iOS 26: only the floating glass pill. A tab bar that is not inside a
            // tab bar controller still paints a full-width band behind the pill by
            // default; a clear background removes that band so the page shows through.
            let a = UITabBarAppearance()
            a.configureWithTransparentBackground()
            a.backgroundColor = .clear
            a.backgroundEffect = nil
            a.shadowColor = .clear
            tabBar.standardAppearance = a
            tabBar.scrollEdgeAppearance = a
            tabBar.backgroundColor = .clear
            tabBar.isTranslucent = true
        }
        var items: [UITabBarItem] = []
        for (i, t) in config.tabs.enumerated() {
            let img = UIImage(systemName: AppConfig.symbol(t.icon))
            items.append(UITabBarItem(title: t.label, image: img, tag: i))
        }
        tabBar.items = items
        tabBar.selectedItem = items.first
    }

    private func classicAppearance() {
        let a = UITabBarAppearance()
        a.configureWithOpaqueBackground()
        a.backgroundColor = config.barColor
        a.shadowColor = UIColor(white: 1, alpha: 0.08)
        for layout in [a.stackedLayoutAppearance, a.inlineLayoutAppearance, a.compactInlineLayoutAppearance] {
            layout.normal.iconColor = config.inactiveColor
            layout.normal.titleTextAttributes = [.foregroundColor: config.inactiveColor, .font: UIFont.systemFont(ofSize: 10, weight: .medium)]
            layout.selected.iconColor = config.activeColor
            layout.selected.titleTextAttributes = [.foregroundColor: config.activeColor, .font: UIFont.systemFont(ofSize: 10, weight: .semibold)]
        }
        tabBar.standardAppearance = a
        tabBar.scrollEdgeAppearance = a
    }

    func tabBar(_ tabBar: UITabBar, didSelect item: UITabBarItem) {
        selectTab(item.tag)
    }

    private func selectTab(_ index: Int) {
        guard index >= 0 && index < config.tabs.count else { return }
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        let tab = config.tabs[index]
        let same = index == lastTag
        lastTag = index

        if tab.action == "chat" {
            openChat()
            return
        }
        // Tapping the tab you are already on scrolls back to the top, like other apps.
        if same, let cur = bridgeVC.webView?.url, score(tab, cur) > 0 {
            bridgeVC.webView?.scrollView.setContentOffset(.zero, animated: true)
            return
        }
        bridgeVC.webView?.load(URLRequest(url: tab.url))
    }

    /// How well a tab matches a page: same path, and every query value on the tab present on the page.
    private func score(_ tab: AppTab, _ url: URL) -> Int {
        if tab.action == "chat" || !config.isOwn(url) { return 0 }
        func clean(_ p: String) -> String {
            var s = p
            while s.count > 1 && s.hasSuffix("/") { s.removeLast() }
            return s.isEmpty ? "/" : s
        }
        if clean(tab.url.path) != clean(url.path) {
            // Extra paths for this tab (Home also lights on the site root).
            let q = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
            return q.isEmpty && tab.match.map(clean).contains(clean(url.path)) ? 1 : 0
        }
        let want = URLComponents(url: tab.url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        let have = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        var s = 1
        for q in want {
            if !have.contains(where: { $0.name == q.name && $0.value == q.value }) { return 0 }
            s += 1
        }
        return s
    }

    private func syncTab(_ url: URL?) {
        guard let url = url else { return }
        let count = useKit ? config.tabs.count : (tabBar.items?.count ?? 0)
        var best = -1, bestScore = 0
        for (i, t) in config.tabs.enumerated() {
            let s = score(t, url)
            if s > bestScore { bestScore = s; best = i }
        }
        // A page no tab points at (a single link, a profile) keeps the last tab lit.
        if best >= 0 && best < count {
            if useKit { kitBar.select(best) } else { tabBar.selectedItem = tabBar.items?[best] }
            lastTag = best
        }
    }

    // MARK: - Chat

    private func openChat() {
        guard let web = bridgeVC.webView else { return }
        web.evaluateJavaScript(chatJS) { [weak self] result, _ in
            guard let self = self else { return }
            if (result as? String) != "ok" {
                // Chat lives on the site's pages; open home, then the chat once it has loaded.
                self.pendingChat = true
                web.load(URLRequest(url: self.config.start))
            }
        }
    }

    // MARK: - Web view state

    private func watchWebView() {
        guard let web = bridgeVC.webView else { return }
        refresh.tintColor = config.activeColor
        refresh.addTarget(self, action: #selector(pullToRefresh), for: .valueChanged)
        // Capacitor turns the page's bounce off, and pull to refresh needs it: a pull at the top
        // has to be able to stretch the page down. Always bounce, even on pages that fit the screen.
        web.scrollView.bounces = true
        web.scrollView.alwaysBounceVertical = true
        web.scrollView.refreshControl = refresh

        observers.append(web.observe(\.url, options: [.new]) { [weak self] w, _ in
            DispatchQueue.main.async { self?.syncTab(w.url) }
        })
        observers.append(web.observe(\.isLoading, options: [.new]) { [weak self] w, _ in
            DispatchQueue.main.async {
                guard let self = self else { return }
                // A new page always starts with the tabs showing.
                if w.isLoading { self.setTabBarHidden(false); self.setRefreshEnabled(true); return }
                self.refresh.endRefreshing()
                self.syncTab(w.url)
                self.finishBoot()
                if self.pendingChat {
                    self.pendingChat = false
                    DispatchQueue.main.asyncAfter(deadline: .now() + 0.8) {
                        w.evaluateJavaScript(self.chatJS, completionHandler: nil)
                    }
                }
            }
        })
    }

    @objc private func pullToRefresh() {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
        bridgeVC.webView?.reload()
        DispatchQueue.main.asyncAfter(deadline: .now() + 8) { [weak self] in self?.refresh.endRefreshing() }
    }

    /// Pull to refresh on or off. The site turns it off where a pull means something else
    /// (Reels, a website open full screen, the galaxy, pop-ups) and every new page starts with it on.
    func setRefreshEnabled(_ on: Bool) {
        guard let web = bridgeVC.webView else { return }
        if on {
            web.scrollView.bounces = true
            web.scrollView.alwaysBounceVertical = true
            if web.scrollView.refreshControl == nil { web.scrollView.refreshControl = refresh }
        } else {
            refresh.endRefreshing()
            web.scrollView.refreshControl = nil
            // No rubber band where a pull means something else (Reels, full screen, the galaxy).
            web.scrollView.bounces = false
            web.scrollView.alwaysBounceVertical = false
        }
    }

    /// Opens a link from a notification, a home screen quick action or a nexurls.com link
    /// tapped in another app. Links to the site stay in the app; others go to Safari.
    func open(_ url: URL) {
        if config.isOwn(url) {
            bridgeVC.webView?.load(URLRequest(url: url))
        } else {
            UIApplication.shared.open(url)
        }
    }

    // MARK: - Offline

    private func buildOfflineView() {
        offlineView.backgroundColor = config.paperColor
        offlineView.isHidden = true
        offlineView.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(offlineView)
        NSLayoutConstraint.activate([
            offlineView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            offlineView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            offlineView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            offlineView.bottomAnchor.constraint(equalTo: bar.topAnchor)
        ])

        let icon = UIImageView(image: UIImage(systemName: "wifi.slash"))
        icon.tintColor = config.activeColor
        icon.contentMode = .scaleAspectFit
        icon.heightAnchor.constraint(equalToConstant: 56).isActive = true

        let title = UILabel()
        title.text = config.offlineTitle
        title.font = .systemFont(ofSize: 22, weight: .bold)
        title.textColor = config.inkColor
        title.textAlignment = .center

        let text = UILabel()
        text.text = config.offlineText
        text.font = .systemFont(ofSize: 16)
        text.textColor = config.mutedColor
        text.textAlignment = .center
        text.numberOfLines = 0

        let button = UIButton(type: .system)
        button.setTitle(config.offlineButton, for: .normal)
        button.titleLabel?.font = .systemFont(ofSize: 17, weight: .semibold)
        button.backgroundColor = config.activeColor
        button.setTitleColor(config.inkColor, for: .normal)
        button.layer.cornerRadius = 24
        button.heightAnchor.constraint(equalToConstant: 48).isActive = true
        button.widthAnchor.constraint(equalToConstant: 200).isActive = true
        button.addTarget(self, action: #selector(retry), for: .touchUpInside)

        let stack = UIStackView(arrangedSubviews: [icon, title, text, button])
        stack.axis = .vertical
        stack.alignment = .center
        stack.spacing = 14
        stack.translatesAutoresizingMaskIntoConstraints = false
        offlineView.addSubview(stack)
        NSLayoutConstraint.activate([
            stack.centerYAnchor.constraint(equalTo: offlineView.centerYAnchor),
            stack.leadingAnchor.constraint(equalTo: offlineView.leadingAnchor, constant: 32),
            stack.trailingAnchor.constraint(equalTo: offlineView.trailingAnchor, constant: -32)
        ])
    }

    private func watchNetwork() {
        monitor.pathUpdateHandler = { [weak self] path in
            DispatchQueue.main.async { self?.setOffline(path.status != .satisfied) }
        }
        monitor.start(queue: DispatchQueue(label: "nexapp.network"))
    }

    private func setOffline(_ off: Bool) {
        if off == isOffline { return }
        isOffline = off
        offlineView.isHidden = !off
        if !off { bridgeVC.webView?.reload() }
    }

    @objc private func retry() {
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
        if monitor.currentPath.status == .satisfied {
            setOffline(false)
            bridgeVC.webView?.reload()
        }
    }
}

/// A fixed, full width bottom bar drawn by the app itself: the same look on every iOS version.
/// Used for the fixed bar style on iOS 26, where Apple's own bar is only the floating pill.
final class KitBar: UIView {
    var onSelect: ((Int) -> Void)?
    private var buttons: [UIControl] = []
    private var icons: [UIImageView] = []
    private var labels: [UILabel] = []
    private var badges: [UILabel] = []
    private var active = UIColor.systemBlue
    private var inactive = UIColor.gray
    private(set) var selected = 0

    func build(tabs: [AppTab], background: UIColor, active: UIColor, inactive: UIColor) {
        self.active = active
        self.inactive = inactive
        backgroundColor = background
        let line = UIView()
        line.backgroundColor = UIColor(white: 0.5, alpha: 0.18)
        line.translatesAutoresizingMaskIntoConstraints = false
        addSubview(line)
        let stack = UIStackView()
        stack.axis = .horizontal
        stack.distribution = .fillEqually
        stack.translatesAutoresizingMaskIntoConstraints = false
        addSubview(stack)
        NSLayoutConstraint.activate([
            line.topAnchor.constraint(equalTo: topAnchor),
            line.leadingAnchor.constraint(equalTo: leadingAnchor),
            line.trailingAnchor.constraint(equalTo: trailingAnchor),
            line.heightAnchor.constraint(equalToConstant: 0.5),
            stack.topAnchor.constraint(equalTo: topAnchor),
            stack.leadingAnchor.constraint(equalTo: leadingAnchor),
            stack.trailingAnchor.constraint(equalTo: trailingAnchor),
            stack.heightAnchor.constraint(equalToConstant: 49)
        ])
        for (i, t) in tabs.enumerated() {
            let b = UIControl()
            b.tag = i
            b.accessibilityLabel = t.label
            b.accessibilityTraits = .button
            b.addTarget(self, action: #selector(tap(_:)), for: .touchUpInside)
            let icon = UIImageView(image: UIImage(systemName: AppConfig.symbol(t.icon)))
            icon.contentMode = .scaleAspectFit
            icon.translatesAutoresizingMaskIntoConstraints = false
            let label = UILabel()
            label.text = t.label
            label.font = .systemFont(ofSize: 10, weight: .medium)
            label.textAlignment = .center
            label.translatesAutoresizingMaskIntoConstraints = false
            let badge = UILabel()
            badge.font = .systemFont(ofSize: 10, weight: .bold)
            badge.textColor = .white
            badge.textAlignment = .center
            badge.backgroundColor = UIColor(red: 0.94, green: 0.27, blue: 0.27, alpha: 1)
            badge.layer.cornerRadius = 8
            badge.clipsToBounds = true
            badge.isHidden = true
            badge.translatesAutoresizingMaskIntoConstraints = false
            b.addSubview(icon)
            b.addSubview(label)
            b.addSubview(badge)
            NSLayoutConstraint.activate([
                icon.topAnchor.constraint(equalTo: b.topAnchor, constant: 7),
                icon.centerXAnchor.constraint(equalTo: b.centerXAnchor),
                icon.heightAnchor.constraint(equalToConstant: 24),
                icon.widthAnchor.constraint(equalToConstant: 28),
                label.topAnchor.constraint(equalTo: icon.bottomAnchor, constant: 2),
                label.leadingAnchor.constraint(equalTo: b.leadingAnchor, constant: 2),
                label.trailingAnchor.constraint(equalTo: b.trailingAnchor, constant: -2),
                badge.topAnchor.constraint(equalTo: b.topAnchor, constant: 3),
                badge.leadingAnchor.constraint(equalTo: icon.centerXAnchor, constant: 6),
                badge.heightAnchor.constraint(equalToConstant: 16),
                badge.widthAnchor.constraint(greaterThanOrEqualToConstant: 16)
            ])
            stack.addArrangedSubview(b)
            buttons.append(b)
            icons.append(icon)
            labels.append(label)
            badges.append(badge)
        }
        select(0)
    }

    @objc private func tap(_ sender: UIControl) {
        select(sender.tag)
        onSelect?(sender.tag)
    }

    func select(_ index: Int) {
        selected = index
        for i in 0..<icons.count {
            let on = i == index
            icons[i].tintColor = on ? active : inactive
            labels[i].textColor = on ? active : inactive
            labels[i].font = .systemFont(ofSize: 10, weight: on ? .semibold : .medium)
            buttons[i].accessibilityTraits = on ? [.button, .selected] : .button
        }
    }

    func setBadge(_ index: Int, _ text: String?) {
        guard index >= 0 && index < badges.count else { return }
        badges[index].text = text.map { " \($0) " }
        badges[index].isHidden = text == nil
    }
}
