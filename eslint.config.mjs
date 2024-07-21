import globals from "globals";
import pluginJs from "@eslint/js";

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
        }
    },
    {
        rules: {
        },
    },
];
