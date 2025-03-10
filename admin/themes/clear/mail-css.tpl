{* $Id: mail-css.tpl 2526 2008-09-14 00:33:53Z vdigital $ *}
/* Theme wipi mail css */

body { background-color:#111; color:#69c; }
#the_page { background: #111 url({$ROOT_URL}template/{$themeconf.template}/mail/text/html/images/mailbody-bg.png)
repeat-y scroll left top; }
#content { background: transparent url({$ROOT_URL}template/{$themeconf.template}/mail/text/html/images/header-bg.png)
no-repeat scroll left top; }
#copyright { background: transparent url({$ROOT_URL}template/{$themeconf.template}/mail/text/html/images/footer-bg.png)
no-repeat scroll left bottom; color: #69c; }
h2 { background-color: #222;color:#eee;background-image:
url({$ROOT_URL}template/{$themeconf.template}/themes/{$themeconf.theme}/images/tableh1_bg.png); }
img { margin: 16px; padding:15px;border:1px solid #eee; -moz-border-radius: 4px; border-radius: 4px 4px; }
img:hover { border:1px solid #69c; -moz-border-radius: 4px; border-radius: 4px 4px; }
a { color: #69c; background: transparent; }
a:hover { color: #f92; }
a.PWG { border: 0px; }
a.PWG .P { color : #f92; }
a.PWG .W { color : #aaa; }
a.PWG .G { color : #69c; }
a.PWG:hover .P { color : #69c; }
a.PWG:hover .G { color : #f92; }