if (window.RVTS) {
  (() => {
    if (RVTS.start > 0) {
      const rvtsUp = document.createElement("div");
      rvtsUp.id = "rvtsUp";
      rvtsUp.style.textAlign = "center";
      rvtsUp.style.fontSize = "120%";
      rvtsUp.style.margin = "10px";

      const firstLink = document.querySelector(".navigationBar A[rel=first]");
      rvtsUp.innerHTML = `
        <a href="${firstLink.href}">${firstLink.innerHTML}</a> |
        <a href="javascript:RVTS.loadUp()">${RVTS.prevMsg}</a>
      `;

      const thumbnails = document.querySelector(".thumbnails, #thumbnails");
      thumbnails.parentNode.insertBefore(rvtsUp, thumbnails);
    }

    Object.assign(RVTS, {
      loading: 0,
      loadingUp: 0,
      adjust: 0,

      loadUp() {
        if (this.loadingUp || this.start <= 0) {
          return;
        }

        let newStart = this.start - this.perPage;
        let reqCount = this.perPage;

        if (newStart < 0) {
          reqCount += newStart;
          newStart = 0;
        }

        document.getElementById("ajaxLoader").style.display = "block";
        this.loadingUp = 1;

        const url = this.ajaxUrlModel.replace("%start%", newStart).replace("%per%", reqCount);
        fetch(url)
          .then((response) => response.text())
          .then((htm) => {
            this.start = newStart;
            const event = new CustomEvent("RVTS_add", { detail: { htm, isAutoScroll: false } });
            window.dispatchEvent(event);

            if (!event.defaultPrevented) {
              this.thumbs.insertAdjacentHTML("afterbegin", htm);
            }

            if (this.start <= 0) {
              document.getElementById("rvtsUp").remove();
            }
          })
          .finally(() => {
            this.loadingUp = 0;

            if (!this.loading) {
              document.getElementById("ajaxLoader").style.display = "none";
            }

            window.dispatchEvent(new Event("RVTS_loaded"));
          });
      },

      doAutoScroll() {
        if (this.loading || this.next >= this.total) {
          return;
        }

        document.getElementById("ajaxLoader").style.display = "block";
        this.loading = 1;

        let url = this.ajaxUrlModel.replace("%start%", this.next).replace("%per%", this.perPage);

        if (this.adjust) {
          url += `&adj=${this.adjust}`;
          this.adjust = 0;
        }

        fetch(url)
          .then((response) => response.text())
          .then((htm) => {
            this.next += this.perPage;
            const event = new CustomEvent("RVTS_add", { detail: { htm, isAutoScroll: true } });
            window.dispatchEvent(event);

            if (!event.defaultPrevented) {
              this.thumbs.insertAdjacentHTML("beforeend", htm);
            }
          })
          .finally(() => {
            this.loading = 0;

            if (!this.loadingUp) {
              document.getElementById("ajaxLoader").style.display = "none";
            }

            window.dispatchEvent(new Event("RVTS_loaded"));
          });
      },

      checkAutoScroll(evt) {
        const thumbsBottom = this.thumbs.offsetTop + this.thumbs.offsetHeight;
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        const windowBottom = window.scrollY + windowHeight;
        const threshold = evt ? 100 : 0; // begin 100 pixels before end

        if (thumbsBottom - threshold <= windowBottom) {
          this.doAutoScroll();
          return true;
        }

        return false;
      },

      engage() {        
        const ajaxLoader = document.createElement("div");
        ajaxLoader.id = "ajaxLoader";
        ajaxLoader.style.display = "none";
        ajaxLoader.style.position = "fixed";
        ajaxLoader.style.bottom = "32px";
        ajaxLoader.style.right = "1%";
        ajaxLoader.style.zIndex = 999;
        ajaxLoader.innerHTML = `<img src="${this.ajaxLoaderImage}" width="128" height="15" alt="Loading...">`;

        this.thumbs = document.querySelector(".thumbnails, #thumbnails");
        this.thumbs.insertAdjacentElement("afterend", ajaxLoader);

        if (window.location.hash === "#top") {
          window.scrollTo(0, 0);
        }

        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        this.adjust = this.thumbs.offsetHeight < windowHeight ? 1 : this.thumbs.offsetHeight > 2 * windowHeight ? -1 : 0;

        window.addEventListener("scroll", () => this.checkAutoScroll());
        window.addEventListener("resize", () => this.checkAutoScroll());

        if (this.checkAutoScroll()) setTimeout(() => this.checkAutoScroll(), 1500);
      },
    });

    const init = () => {
      if (window.location.hash === "#top") {
        window.scrollTo(0, 0);
      }

      setTimeout(() => RVTS.engage(), 150);
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }

    if (window.history.replaceState) {
      const iniStart = RVTS.start;
      window.addEventListener("RVTS_loaded", () => {
        window.addEventListener("unload", () => {
            const threshold = Math.max(0, window.scrollY - 60);
            const elems = RVTS.thumbs.querySelectorAll("li");

            for (const elem of elems) {
              const offset = elem.getBoundingClientRect();

              if (offset.top >= threshold) {
                const start = RVTS.start + [...elems].indexOf(elem);
                const delta = start - iniStart;

                if (delta < 0 || delta >= RVTS.perPage) {
                  const url = start ? RVTS.urlModel.replace("%start%", start) : RVTS.urlModel.replace("/start-%start%", "");
                  window.history.replaceState(null, "", `${url}#top`);
                }

                break;
              }
            }
          }, { once: true } // unload
        );
      });
    }
  })();
}
