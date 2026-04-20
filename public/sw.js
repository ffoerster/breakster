if (!self.define) {
    let e,
        s = {};
    const i = (i, b) => (
        (i = new URL(i + ".js", b).href),
        s[i] ||
            new Promise((s) => {
                if ("document" in self) {
                    const e = document.createElement("script");
                    ((e.src = i), (e.onload = s), document.head.appendChild(e));
                } else ((e = i), importScripts(i), s());
            }).then(() => {
                let e = s[i];
                if (!e) throw new Error(`Module ${i} didn’t register its module`);
                return e;
            })
    );
    self.define = (b, r) => {
        const n = e || ("document" in self ? document.currentScript.src : "") || location.href;
        if (s[n]) return;
        let a = {};
        const d = (e) => i(e, n),
            c = { module: { uri: n }, exports: a, require: d };
        s[n] = Promise.all(b.map((e) => c[e] || d(e))).then((e) => (r(...e), a));
    };
}
define(["./workbox-bdbbf67e"], function (e) {
    "use strict";
    (self.addEventListener("message", (e) => {
        e.data && "SKIP_WAITING" === e.data.type && self.skipWaiting();
    }),
        e.precacheAndRoute(
            [
                {
                    url: "build/assets/theme-DKTYeHY6.css",
                    revision: "4584a868caa44ba68833a7b1ba8ab274",
                },
                {
                    url: "build/assets/runtime-core.esm-bundler-CVx1ri6n.js",
                    revision: "3234964b58b6e8729198d899ca8a767a",
                },
                {
                    url: "build/assets/qrcode.vue.esm--iRO0ekZ.js",
                    revision: "dd2693adca4d12ee50dfe78fcf315543",
                },
                {
                    url: "build/assets/howler-DX6xG5vv.js",
                    revision: "288e2904becfd30239a6997ac81d17d5",
                },
                {
                    url: "build/assets/confetti.module-BYbDf9GK.js",
                    revision: "7a95ec7acb4f26578ec45ce97d76a039",
                },
                {
                    url: "build/assets/chunk-Bni-sYT0.js",
                    revision: "6ea83fa7e10f46f9b5896f936be613bb",
                },
                {
                    url: "build/assets/app-Bc3UxtQ8.css",
                    revision: "829c8308d781dbc2f3de010a9630ebe5",
                },
                {
                    url: "build/assets/app-BQj1UXpu.css",
                    revision: "3e6b6476f0096e2d5008b048b1e7077e",
                },
                {
                    url: "build/assets/app-BM4NV0pQ.js",
                    revision: "978fb9f8ed9283bf0310e25220a060b4",
                },
                { url: "build/manifest.json", revision: "6b0df0d1a5ea2170982e58d16ee0fb09" },
                { url: "build/manifest.webmanifest", revision: "0ffb35318ebe241c7f280cd9bb2b6449" },
                { url: "pwa/icon-512.png", revision: "578c7e80b436e271cb97a588da0b03c9" },
                { url: "pwa/icon-192.png", revision: "d290915f24fbfc67976e15a2d33eb066" },
                { url: "pwa/apple-touch-icon.png", revision: "155076e97b9f839f09ab5c323de3c8a9" },
                { url: "manifest.webmanifest", revision: "0ffb35318ebe241c7f280cd9bb2b6449" },
            ],
            {},
        ),
        e.cleanupOutdatedCaches());
});
