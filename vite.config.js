import { defineConfig } from "vite-plus";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { VitePWA } from "vite-plugin-pwa";
import { resolve } from "node:path";

export default defineConfig({
    staged: {
        "*": "vp check --fix",
    },
    fmt: {
        ignorePatterns: ["storage/**", "vendor/**", ".ddev/**"],
        semi: true,
        sortPackageJson: true,
    },
    lint: {
        ignorePatterns: ["storage/**", "vendor/**", ".ddev/**"],
        options: { typeAware: true, typeCheck: true },
    },
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/filament/backend/theme.css",
                "resources/js/app.js",
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        VitePWA({
            strategies: "generateSW",
            injectRegister: null,
            registerType: "prompt",
            outDir: "public",
            devOptions: {
                enabled: false,
            },
            manifest: {
                name: "Breakster",
                short_name: "Breakster",
                description: "Breakster - Timeline Music Game.",
                start_url: "/",
                scope: "/",
                display: "standalone",
                orientation: "portrait",
                theme_color: "#06b6d4",
                background_color: "#0b0f1a",
                icons: [
                    {
                        src: "/pwa/icon-192.png",
                        sizes: "192x192",
                        type: "image/png",
                        purpose: "any maskable",
                    },
                    {
                        src: "/pwa/icon-512.png",
                        sizes: "512x512",
                        type: "image/png",
                        purpose: "any maskable",
                    },
                ],
            },
            workbox: {
                globDirectory: resolve(process.cwd(), "public"),
                globPatterns: [
                    "build/assets/**/*.{js,css,woff,woff2,ttf,eot,svg,png,jpg,jpeg,webp,ico}",
                    "build/manifest.json",
                    "build/manifest.webmanifest",
                    "pwa/*.png",
                ],
                navigateFallback: undefined,
            },
        }),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
