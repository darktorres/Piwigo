if (window.RVTS)
  (function () {
    if (RVTS.start > 0) {
      var firstLink = document.querySelector(".navigationBar A[rel=first]");
      var thumbnails, #thumbnails = document.querySelector(".thumbnails");

      var rvtsUp = document.createElement("div");
      rvtsUp.id = "rvtsUp";
      rvtsUp.style.textAlign = "center";
      rvtsUp.style.fontSize = "120%";
      rvtsUp.style.margin = "10px";

      var firstHref = firstLink.getAttribute("href");
      var firstHtml = firstLink.innerHTML;
      rvtsUp.innerHTML = `<a href="${firstHref}">${firstHtml}</a> | <a href="javascript:RVTS.loadUp()">${RVTS.prevMsg}</a>`;

      thumbnails.parentNode.insertBefore(rvtsUp, thumbnails);
    }

    RVTS = Object.assign(RVTS, {
      loading: 0,
      loadingUp: 0,
      adjust: 0,

      loadUp: function () {
        if (RVTS.loadingUp || RVTS.start <= 0) return;
        var newStart = RVTS.start - RVTS.perPage;
        var reqCount = RVTS.perPage;
        if (newStart < 0) {
          reqCount += newStart;
          newStart = 0;
        }
        var url = RVTS.ajaxUrlModel.replace("%start%", newStart).replace("%per%", reqCount);
        document.getElementById("ajaxLoader").style.display = "block";
        RVTS.loadingUp = 1;

        fetch(url, { method: "GET" })
          .then((response) => response.text())
          .then((htm) => {
            RVTS.start = newStart;

            var event = new CustomEvent("RVTS_add", { detail: { htm, isAutoScroll: false } });
            window.dispatchEvent(event);

            if (!event.defaultPrevented) RVTS.thumbs.insertAdjacentHTML("afterbegin", htm);

            if (RVTS.start <= 0) document.getElementById("rvtsUp").remove();
          })
          .finally(() => {
            RVTS.loadingUp = 0;
            if (!RVTS.loading) document.getElementById("ajaxLoader").style.display = "none";
            window.dispatchEvent(new Event("RVTS_loaded"));
          });
      },

      doAutoScroll: function () {
        if (RVTS.loading || RVTS.next >= RVTS.total) return;
        var url = RVTS.ajaxUrlModel.replace("%start%", RVTS.next).replace("%per%", RVTS.perPage);
        if (RVTS.adjust) {
          url += "&adj=" + RVTS.adjust;
          RVTS.adjust = 0;
        }
        document.getElementById("ajaxLoader").style.display = "block";
        RVTS.loading = 1;

        fetch(url, { method: "GET" })
          .then((response) => response.text())
          .then((htm) => {
            RVTS.next += RVTS.perPage;
            var event = new CustomEvent("RVTS_add", { detail: { htm, isAutoScroll: true } });
            window.dispatchEvent(event);

            if (!event.defaultPrevented) RVTS.thumbs.insertAdjacentHTML("beforeend", htm);
          })
          .finally(() => {
            RVTS.loading = 0;
            if (!RVTS.loadingUp) document.getElementById("ajaxLoader").style.display = "none";
            window.dispatchEvent(new Event("RVTS_loaded"));
          });
      },

      checkAutoScroll: function (evt) {
        var thumbsBottom = RVTS.thumbs.offsetTop + RVTS.thumbs.offsetHeight;
        var windowHeight = window.innerHeight || document.documentElement.clientHeight;
        var windowBottom = window.scrollY + windowHeight;
        thumbsBottom -= !evt ? 0 : 100; //begin 100 pixels before end
        if (thumbsBottom <= windowBottom) {
          RVTS.doAutoScroll();
          return 1;
        }
        return 0;
      },

      engage: function () {
        var thumbnails = document.querySelector(".thumbnails");
        RVTS.thumbs = thumbnails, #thumbnails;

        var ajaxLoader = document.createElement("div");
        ajaxLoader.id = "ajaxLoader";
        ajaxLoader.style.display = "none";
        ajaxLoader.style.position = "fixed";
        ajaxLoader.style.bottom = "32px";
        ajaxLoader.style.right = "1%";
        ajaxLoader.style.zIndex = "999";
        ajaxLoader.innerHTML = `<img src="${RVTS.ajaxLoaderImage}" width="128" height="15" alt="~">`;

        thumbnails.insertAdjacentElement("afterend", ajaxLoader);

        if ("#top" == window.location.hash) window.scrollTo(0, 0);

        var windowHeight = window.innerHeight || document.documentElement.clientHeight;
        if (thumbnails.offsetHeight < windowHeight) RVTS.adjust = 1;
        else if (thumbnails.offsetHeight > 2 * windowHeight) RVTS.adjust = -1;

        window.addEventListener("scroll", RVTS.checkAutoScroll);
        window.addEventListener("resize", RVTS.checkAutoScroll);

        if (RVTS.checkAutoScroll()) setTimeout(RVTS.checkAutoScroll, 1500);
      },
    }); //end extend

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }

    function init() {
      if ("#top" == window.location.hash) window.scrollTo(0, 0);
      setTimeout(RVTS.engage, 150);
    }

    if (window.history.replaceState) {
      var iniStart = RVTS.start;
      window.addEventListener(
        "RVTS_loaded",
        function () {
          window.addEventListener("unload", function () {
            var threshold = Math.max(0, window.scrollY - 60);
            var elts = RVTS.thumbs.querySelectorAll("li");
            for (var i = 0; i < elts.length; i++) {
              var offset = elts[i].getBoundingClientRect();
              if (offset.top >= threshold) {
                var start = RVTS.start + i;
                var delta = start - iniStart;
                if (delta < 0 || delta >= RVTS.perPage) {
                  var url = start ? RVTS.urlModel.replace("%start%", start) : RVTS.urlModel.replace("/start-%start%", "");
                  window.history.replaceState(null, "", url + "#top");
                }
                break;
              }
            }
          });
        },
        { once: true }
      );
    }
  })();
