import globals from "globals";
import pluginJs from "@eslint/js";
import jquery from "eslint-plugin-jquery";

export default [{
        ignores: ["**/node_modules/*", "vendor/*"]
    },
    {
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.jquery
            }
        }
    },
    pluginJs.configs.recommended,
    {
        plugins: {
            jquery
        }
    },
    {
        rules: {
            "jquery/no-ajax": "warn",
            "jquery/no-animate": "warn",
            "jquery/no-attr": "warn",
            "jquery/no-bind": "warn",
            "jquery/no-class": "warn",
            "jquery/no-clone": "warn",
            "jquery/no-closest": "warn",
            "jquery/no-css": "warn",
            "jquery/no-data": "warn",
            "jquery/no-deferred": "warn",
            "jquery/no-delegate": "warn",
            "jquery/no-each": "warn",
            "jquery/no-extend": "warn",
            "jquery/no-fade": "warn",
            "jquery/no-filter": "warn",
            "jquery/no-find": "warn",
            "jquery/no-global-eval": "warn",
            "jquery/no-grep": "warn",
            "jquery/no-has": "warn",
            "jquery/no-html": "warn",
            "jquery/no-in-array": "warn",
            "jquery/no-is-array": "warn",
            "jquery/no-is-function": "warn",
            "jquery/no-load": "warn",
            "jquery/no-map": "warn",
            "jquery/no-merge": "warn",
            "jquery/no-param": "warn",
            "jquery/no-parent": "warn",
            "jquery/no-parents": "warn",
            "jquery/no-parse-html": "warn",
            "jquery/no-prop": "warn",
            "jquery/no-proxy": "warn",
            "jquery/no-ready": "warn",
            "jquery/no-serialize": "warn",
            "jquery/no-show": "warn",
            "jquery/no-size": "warn",
            "jquery/no-sizzle": "warn",
            "jquery/no-slide": "warn",
            "jquery/no-submit": "warn",
            "jquery/no-text": "warn",
            "jquery/no-toggle": "warn",
            "jquery/no-trigger": "warn",
            "jquery/no-trim": "warn",
            "jquery/no-val": "warn",
            "jquery/no-wrap": "warn",
        },
    },
];
