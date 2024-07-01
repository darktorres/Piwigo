<?php
/**
 * Smarty plugin
 *
 * @subpackage PluginsFunction
 */
/**
 * Smarty {fetch} plugin
 * Type:     function
 * Name:     fetch
 * Purpose:  fetch file, web or ftp data and display results
 *
 * @link   https://www.smarty.net/manual/en/language.function.fetch.php {fetch}
 *         (Smarty online manual)
 *
 * @param array                    $params   parameters
 * @param Smarty_Internal_Template $template template object
 *
 * @return string|null if the assign parameter is passed, Smarty assigns the result to a template variable
 */
function smarty_function_fetch(
    $params,
    $template
) {
    if (empty($params['file'])) {
        trigger_error("[plugin] fetch parameter 'file' cannot be empty", E_USER_NOTICE);
        return;
    }

    // strip file protocol
    if (stripos((string) $params['file'], 'file://') === 0) {
        $params['file'] = substr((string) $params['file'], 7);
    }

    $protocol = strpos((string) $params['file'], '://');
    if ($protocol !== false) {
        $protocol = strtolower(substr((string) $params['file'], 0, $protocol));
    }

    if ($template->smarty->security_policy !== null) {
        if ($protocol) {
            // remote resource (or php stream, …)
            if (! $template->smarty->security_policy->isTrustedUri(
                $params['file']
            )) {
                return;
            }
        } elseif (! $template->smarty->security_policy->isTrustedResourceDir($params['file'])) {
            // local file
            return;
        }
    }

    $content = '';
    if ($protocol === 'http') {
        // http fetch
        if ($uri_parts = parse_url((string) $params['file'])) {
            // set defaults
            $host = $uri_parts['host'];
            $server_name = $uri_parts['host'];
            $timeout = 30;
            $accept = 'image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*';
            $agent = 'Smarty Template Engine ' . Smarty::SMARTY_VERSION;
            $referer = '';
            $uri = empty($uri_parts['path']) ? '/' : $uri_parts['path'];
            $uri .= empty($uri_parts['query']) ? '' : '?' . $uri_parts['query'];
            $_is_proxy = false;
            $port = empty($uri_parts['port']) ? 80 : $uri_parts['port'];

            if (isset($uri_parts['user']) && ($uri_parts['user'] !== '' && $uri_parts['user'] !== '0')) {
                $user = $uri_parts['user'];
            }

            if (isset($uri_parts['pass']) && ($uri_parts['pass'] !== '' && $uri_parts['pass'] !== '0')) {
                $pass = $uri_parts['pass'];
            }

            // loop through parameters, setup headers
            foreach ($params as $param_key => $param_value) {
                switch ($param_key) {
                    case 'file':
                    case 'assign':
                    case 'assign_headers':
                        break;
                    case 'user':
                        if (! empty($param_value)) {
                            $user = $param_value;
                        }

                        break;
                    case 'pass':
                        if (! empty($param_value)) {
                            $pass = $param_value;
                        }

                        break;
                    case 'accept':
                        if (! empty($param_value)) {
                            $accept = $param_value;
                        }

                        break;
                    case 'header':
                        if (! empty($param_value)) {
                            if (! preg_match('![\w\d-]+: .+!', (string) $param_value)) {
                                trigger_error(
                                    sprintf("[plugin] invalid header format '%s'", $param_value),
                                    E_USER_NOTICE
                                );
                                return;
                            }

                            $extra_headers[] = $param_value;

                        }

                        break;
                    case 'proxy_host':
                        if (! empty($param_value)) {
                            $proxy_host = $param_value;
                        }

                        break;
                    case 'proxy_port':
                        if (! preg_match('!\D!', (string) $param_value)) {
                            $proxy_port = (int) $param_value;
                        } else {
                            trigger_error(
                                sprintf("[plugin] invalid value for attribute '%s'", $param_key),
                                E_USER_NOTICE
                            );
                            return;
                        }

                        break;
                    case 'agent':
                        if (! empty($param_value)) {
                            $agent = $param_value;
                        }

                        break;
                    case 'referer':
                        if (! empty($param_value)) {
                            $referer = $param_value;
                        }

                        break;
                    case 'timeout':
                        if (! preg_match('!\D!', (string) $param_value)) {
                            $timeout = (int) $param_value;
                        } else {
                            trigger_error(
                                sprintf("[plugin] invalid value for attribute '%s'", $param_key),
                                E_USER_NOTICE
                            );
                            return;
                        }

                        break;
                    default:
                        trigger_error(sprintf("[plugin] unrecognized attribute '%s'", $param_key), E_USER_NOTICE);
                        return;
                }
            }

            if (! empty($proxy_host) && $proxy_port !== 0) {
                $_is_proxy = true;
                $fp = fsockopen($proxy_host, $proxy_port, $errno, $errstr, $timeout);
            } else {
                $fp = fsockopen($server_name, $port, $errno, $errstr, $timeout);
            }

            if (! $fp) {
                trigger_error(sprintf('[plugin] unable to fetch: %s (%d)', $errstr, $errno), E_USER_NOTICE);
                return;
            }

            if ($_is_proxy) {
                fwrite($fp, 'GET ' . $params['file'] . " HTTP/1.0\r\n");
            } else {
                fwrite($fp, "GET {$uri} HTTP/1.0\r\n");
            }

            if (! empty($host)) {
                fwrite($fp, "Host: {$host}\r\n");
            }

            if (! empty($accept)) {
                fwrite($fp, "Accept: {$accept}\r\n");
            }

            if (! empty($agent)) {
                fwrite($fp, "User-Agent: {$agent}\r\n");
            }

            if (! empty($referer)) {
                fwrite($fp, "Referer: {$referer}\r\n");
            }

            if (isset($extra_headers) && is_array($extra_headers)) {
                foreach ($extra_headers as $curr_header) {
                    fwrite($fp, $curr_header . "\r\n");
                }
            }

            if (! empty($user) && ! empty($pass)) {
                fwrite($fp, 'Authorization: BASIC ' . base64_encode(sprintf('%s:%s', $user, $pass)) . "\r\n");
            }

            fwrite($fp, "\r\n");
            while (! feof($fp)) {
                $content .= fgets($fp, 4096);
            }

            fclose($fp);
            $csplit = preg_split("!\r\n\r\n!", $content, 2);
            $content = $csplit[1];
            if (! empty($params['assign_headers'])) {
                $template->assign($params['assign_headers'], preg_split("!\r\n!", $csplit[0]));
            }

        } else {
            trigger_error('[plugin fetch] unable to parse URL, check syntax', E_USER_NOTICE);
            return;
        }
    } else {
        $content = @file_get_contents($params['file']);
        if ($content === false) {
            throw new SmartyException("{fetch} cannot read resource '" . $params['file'] . "'");
        }
    }

    if (! empty($params['assign'])) {
        $template->assign($params['assign'], $content);
    } else {
        return $content;
    }
}
