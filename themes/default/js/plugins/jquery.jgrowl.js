!(function (i) {
  "function" == typeof define && define.amd
    ? define(["jquery"], i)
    : "object" == typeof module && module.exports
    ? (module.exports = function (e, t) {
        return void 0 === t && (t = "undefined" != typeof window ? require("jquery") : require("jquery")(e)), i(t), t;
      })
    : i(jQuery);
})(function (a) {
  (a.jGrowl = function (e, t) {
    0 === a("#jGrowl").length &&
      a('<div id="jGrowl"></div>')
        .addClass((t && t.position ? t : a.jGrowl.defaults).position)
        .appendTo((t && t.appendTo ? t : a.jGrowl.defaults).appendTo),
      a("#jGrowl").jGrowl(e, t);
  }),
    (a.fn.jGrowl = function (e, t) {
      var i;
      if ((void 0 === t && a.isPlainObject(e) && (e = (t = e).message), a.isFunction(this.each)))
        return (
          (i = arguments),
          this.each(function () {
            void 0 === a(this).data("jGrowl.instance") &&
              (a(this).data("jGrowl.instance", a.extend(new a.fn.jGrowl(), { notifications: [], element: null, interval: null })),
              a(this).data("jGrowl.instance").startup(this)),
              a.isFunction(a(this).data("jGrowl.instance")[e])
                ? a(this).data("jGrowl.instance")[e].apply(a(this).data("jGrowl.instance"), a.makeArray(i).slice(1))
                : a(this).data("jGrowl.instance").create(e, t);
          })
        );
    }),
    a.extend(a.fn.jGrowl.prototype, {
      defaults: {
        pool: 0,
        header: "",
        group: "",
        sticky: !1,
        position: "top-right",
        appendTo: "body",
        glue: "after",
        theme: "default",
        themeState: "highlight",
        corners: "10px",
        check: 250,
        life: 3e3,
        closeDuration: "normal",
        openDuration: "normal",
        easing: "swing",
        closer: !0,
        closeTemplate: "&times;",
        closerTemplate: "<div>[ close all ]</div>",
        log: function () {},
        beforeOpen: function () {},
        afterOpen: function () {},
        open: function () {},
        beforeClose: function () {},
        close: function () {},
        click: function () {},
        animateOpen: { opacity: "show" },
        animateClose: { opacity: "hide" },
      },
      notifications: [],
      element: null,
      interval: null,
      create: function (e, t) {
        t = a.extend({}, this.defaults, t);
        void 0 !== t.speed && ((t.openDuration = t.speed), (t.closeDuration = t.speed)),
          this.notifications.push({ message: e, options: t }),
          t.log.apply(this.element, [this.element, e, t]);
      },
      render: function (e) {
        var t = this,
          i = e.message,
          n = e.options,
          o =
            ((n.themeState = "" === n.themeState ? "" : "ui-state-" + n.themeState),
            a("<div/>")
              .addClass("jGrowl-notification alert " + n.themeState + " ui-corner-all" + (void 0 !== n.group && "" !== n.group ? " " + n.group : ""))
              .append(a("<button/>").addClass("jGrowl-close").html(n.closeTemplate))
              .append(a("<div/>").addClass("jGrowl-header").html(n.header))
              .append(a("<div/>").addClass("jGrowl-message").html(i))
              .data("jGrowl", n)
              .addClass(n.theme)
              .children(".jGrowl-close")
              .bind("click.jGrowl", function () {
                return a(this).parent().trigger("jGrowl.beforeClose"), !1;
              })
              .parent());
        a(o)
          .bind("mouseover.jGrowl", function () {
            a(".jGrowl-notification", t.element).data("jGrowl.pause", !0);
          })
          .bind("mouseout.jGrowl", function () {
            a(".jGrowl-notification", t.element).data("jGrowl.pause", !1);
          })
          .bind("jGrowl.beforeOpen", function () {
            !1 !== n.beforeOpen.apply(o, [o, i, n, t.element]) && a(this).trigger("jGrowl.open");
          })
          .bind("jGrowl.open", function () {
            !1 !== n.open.apply(o, [o, i, n, t.element]) &&
              ("after" == n.glue ? a(".jGrowl-notification:last", t.element).after(o) : a(".jGrowl-notification:first", t.element).before(o),
              a(this).animate(n.animateOpen, n.openDuration, n.easing, function () {
                !1 === a.support.opacity && this.style.removeAttribute("filter"),
                  null !== a(this).data("jGrowl") && void 0 !== a(this).data("jGrowl") && (a(this).data("jGrowl").created = new Date()),
                  a(this).trigger("jGrowl.afterOpen");
              }));
          })
          .bind("jGrowl.afterOpen", function () {
            n.afterOpen.apply(o, [o, i, n, t.element]);
          })
          .bind("click", function () {
            n.click.apply(o, [o, i, n, t.element]);
          })
          .bind("jGrowl.beforeClose", function () {
            !1 !== n.beforeClose.apply(o, [o, i, n, t.element]) && a(this).trigger("jGrowl.close");
          })
          .bind("jGrowl.close", function () {
            a(this).data("jGrowl.pause", !0),
              a(this).animate(n.animateClose, n.closeDuration, n.easing, function () {
                (!a.isFunction(n.close) || !1 !== n.close.apply(o, [o, i, n, t.element])) && a(this).remove();
              });
          })
          .trigger("jGrowl.beforeOpen"),
          "" !== n.corners && void 0 !== a.fn.corner && a(o).corner(n.corners),
          1 < a(".jGrowl-notification:parent", t.element).length &&
            0 === a(".jGrowl-closer", t.element).length &&
            !1 !== this.defaults.closer &&
            a(this.defaults.closerTemplate)
              .addClass("jGrowl-closer " + this.defaults.themeState + " ui-corner-all")
              .addClass(this.defaults.theme)
              .appendTo(t.element)
              .animate(this.defaults.animateOpen, this.defaults.speed, this.defaults.easing)
              .bind("click.jGrowl", function () {
                a(this).siblings().trigger("jGrowl.beforeClose"),
                  a.isFunction(t.defaults.closer) && t.defaults.closer.apply(a(this).parent()[0], [a(this).parent()[0]]);
              });
      },
      update: function () {
        a(this.element)
          .find(".jGrowl-notification:parent")
          .each(function () {
            void 0 !== a(this).data("jGrowl") &&
              void 0 !== a(this).data("jGrowl").created &&
              a(this).data("jGrowl").created.getTime() + parseInt(a(this).data("jGrowl").life, 10) < new Date().getTime() &&
              !0 !== a(this).data("jGrowl").sticky &&
              (void 0 === a(this).data("jGrowl.pause") || !0 !== a(this).data("jGrowl.pause")) &&
              a(this).trigger("jGrowl.beforeClose");
          }),
          0 < this.notifications.length &&
            (0 === this.defaults.pool || a(this.element).find(".jGrowl-notification:parent").length < this.defaults.pool) &&
            this.render(this.notifications.shift()),
          a(this.element).find(".jGrowl-notification:parent").length < 2 &&
            a(this.element)
              .find(".jGrowl-closer")
              .animate(this.defaults.animateClose, this.defaults.speed, this.defaults.easing, function () {
                a(this).remove();
              });
      },
      startup: function (e) {
        (this.element = a(e).addClass("jGrowl").append('<div class="jGrowl-notification"></div>')),
          (this.interval = setInterval(function () {
            var t = a(e).data("jGrowl.instance");
            if (void 0 !== t)
              try {
                t.update();
              } catch (e) {
                throw (t.shutdown(), e);
              }
          }, parseInt(this.defaults.check, 10)));
      },
      shutdown: function () {
        try {
          a(this.element).removeClass("jGrowl").find(".jGrowl-notification").trigger("jGrowl.close").parent().empty();
        } catch (e) {
          throw e;
        } finally {
          clearInterval(this.interval);
        }
      },
      close: function () {
        a(this.element)
          .find(".jGrowl-notification")
          .each(function () {
            a(this).trigger("jGrowl.beforeClose");
          });
      },
    }),
    (a.jGrowl.defaults = a.fn.jGrowl.prototype.defaults);
});