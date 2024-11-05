import globals from "globals";
import pluginJs from "@eslint/js";
import no_jquery from "eslint-plugin-no-jquery";

export default [
    // pluginJs.configs.recommended,

    {
        ignores: ["_data/*", "galleries/*", "**/node_modules/*", "vendor/*"]
    },

    {
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.jquery
            }
        }
    },

    {
        plugins: {
            no_jquery
        }
    },
    
    {
        rules: {
            'no_jquery/no-ajax': 'warn',
            'no_jquery/no-and-self': 'warn',
            'no_jquery/no-animate': 'warn',
            'no_jquery/no-attr': 'warn',
            'no_jquery/no-bind': 'warn',
            'no_jquery/no-box-model': 'warn',
            'no_jquery/no-browser': 'warn',
            'no_jquery/no-camel-case': 'warn',
            'no_jquery/no-class': 'warn',
            'no_jquery/no-clone': 'warn',
            'no_jquery/no-closest': 'warn',
            'no_jquery/no-contains': 'warn',
            'no_jquery/no-context-prop': 'warn',
            'no_jquery/no-css': 'warn',
            'no_jquery/no-data': 'warn',
            'no_jquery/no-deferred': 'warn',
            'no_jquery/no-delegate': 'warn',
            'no_jquery/no-each-collection': 'warn',
            'no_jquery/no-each-util': 'warn',
            'no_jquery/no-error': 'warn',
            'no_jquery/no-error-shorthand': 'warn',
            'no_jquery/no-escape-selector': 'warn',
            'no_jquery/no-event-shorthand': 'warn',
            'no_jquery/no-extend': 'warn',
            'no_jquery/no-fade': 'warn',
            'no_jquery/no-filter': 'warn',
            'no_jquery/no-find-collection': 'warn',
            'no_jquery/no-find-util': 'warn',
            'no_jquery/no-fx-interval': 'warn',
            'no_jquery/no-global-eval': 'warn',
            'no_jquery/no-grep': 'warn',
            'no_jquery/no-has': 'warn',
            'no_jquery/no-hold-ready': 'warn',
            'no_jquery/no-html': 'warn',
            'no_jquery/no-in-array': 'warn',
            'no_jquery/no-is': 'warn',
            'no_jquery/no-is-array': 'warn',
            'no_jquery/no-is-empty-object': 'warn',
            'no_jquery/no-is-function': 'warn',
            'no_jquery/no-is-numeric': 'warn',
            'no_jquery/no-is-plain-object': 'warn',
            'no_jquery/no-is-window': 'warn',
            'no_jquery/no-jquery-constructor': 'warn',
            'no_jquery/no-live': 'warn',
            'no_jquery/no-load': 'warn',
            'no_jquery/no-load-shorthand': 'warn',
            'no_jquery/no-map-collection': 'warn',
            'no_jquery/no-map-util': 'warn',
            'no_jquery/no-merge': 'warn',
            'no_jquery/no-node-name': 'warn',
            'no_jquery/no-noop': 'warn',
            'no_jquery/no-now': 'warn',
            'no_jquery/no-on-ready': 'warn',
            'no_jquery/no-other-methods': 'warn',
            'no_jquery/no-other-utils': 'warn',
            'no_jquery/no-param': 'warn',
            'no_jquery/no-parent': 'warn',
            'no_jquery/no-parents': 'warn',
            'no_jquery/no-parse-html': 'warn',
            'no_jquery/no-parse-json': 'warn',
            'no_jquery/no-parse-xml': 'warn',
            'no_jquery/no-prop': 'warn',
            'no_jquery/no-proxy': 'warn',
            'no_jquery/no-ready-shorthand': 'warn',
            'no_jquery/no-selector-prop': 'warn',
            'no_jquery/no-serialize': 'warn',
            'no_jquery/no-size': 'warn',
            'no_jquery/no-sizzle': [ 'warn', { allowPositional: false, allowOther: true } ],
            'no_jquery/no-slide': 'warn',
            'no_jquery/no-sub': 'warn',
            'no_jquery/no-support': 'warn',
            'no_jquery/no-text': 'warn',
            'no_jquery/no-trigger': 'warn',
            'no_jquery/no-trim': 'warn',
            'no_jquery/no-type': 'warn',
            'no_jquery/no-unique': 'warn',
            'no_jquery/no-unload-shorthand': 'warn',
            'no_jquery/no-val': 'warn',
            'no_jquery/no-visibility': 'warn',
            'no_jquery/no-when': 'warn',
            'no_jquery/no-wrap': 'warn',
            'no_jquery/variable-pattern': 'error',
        },
    },
];
