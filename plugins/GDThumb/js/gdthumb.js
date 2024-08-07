var GDThumb = {
  max_height: 200,
  margin: 10,
  max_first_thumb_width: 0.7,
  big_thumb: null,
  big_thumb_block: false,
  check_pv: false,
  small_thumb: null,
  method: "crop",
  t: new Array(),
  do_merge: false,

  // Initialize plugin logic, perform necessary steps
  setup: function (method, max_height, margin, do_merge, big_thumb, check_pv) {
    document.querySelector("ul#thumbnails").classList.add("thumbnails");

    GDThumb.max_height = max_height;
    GDThumb.margin = margin;
    GDThumb.method = method;
    GDThumb.check_pv = check_pv;
    GDThumb.do_merge = do_merge;
    GDThumb.big_thumb = big_thumb;

    window.addEventListener("RVTS_loaded", function () {
      GDThumb.init();
    });
    GDThumb.init();
  },

  init: function () {
    var mainlists = document.querySelectorAll("ul.thumbnails");
    if (mainlists.length > 0) {
      if (GDThumb.do_merge) {
        GDThumb.merge();
      }

      GDThumb.build();
      window.addEventListener("RVTS_loaded", GDThumb.build);

      function debounce(func, wait) {
        let timeout;
        return function (...args) {
          const context = this;
          clearTimeout(timeout);
          timeout = setTimeout(() => func.apply(context, args), wait);
        };
      }

      const resizeObserver = new ResizeObserver(
        debounce(() => {
          GDThumb.process();
        }, 200)
      ); // Adjust the wait time (200 ms) as needed

      mainlists.forEach(function (mainlist) {
        resizeObserver.observe(mainlist);
      });

      document.querySelectorAll("ul.thumbnails .thumbLegend.overlay").forEach(function (overlay) {
        overlay.addEventListener("click", function () {
          window.location.href = this.parentElement.querySelector("a").href;
        });
      });

      document.querySelectorAll("ul.thumbnails .thumbLegend.overlay-ex").forEach(function (overlayEx) {
        overlayEx.addEventListener("click", function () {
          window.location.href = this.parentElement.querySelector("a").href;
        });
      });
    }
  },

  // Merge categories and picture lists
  merge: function () {
    var mainlists = document.querySelectorAll(".content ul.thumbnails");
    if (mainlists.length < 2) {
      // there is only one list of elements
    } else {
      document.querySelectorAll(".thumbnailCategories li").forEach(function (li) {
        li.classList.add("album");
      });
      var thumbnailsHTML = document.querySelector(".content ul#thumbnails").innerHTML;
      document.querySelector(".thumbnailCategories").insertAdjacentHTML("beforeend", thumbnailsHTML);
      var thumbnails = document.querySelector("ul#thumbnails");
      if (thumbnails) {
        thumbnails.remove();
      }
      var loader = document.querySelectorAll("div.loader");
      if (loader.length > 1) {
        loader[1].remove();
      }
    }
  },

  // Build thumb metadata
  build: function () {
    if (
      GDThumb.method === "square" &&
      GDThumb.big_thumb !== null &&
      (GDThumb.big_thumb.height !== GDThumb.big_thumb.width || GDThumb.big_thumb.height < GDThumb.max_height)
    ) {
      var main_width = document.querySelector("ul.thumbnails").clientWidth;
      var max_col_count = Math.floor(main_width / GDThumb.max_height);
      var thumb_width = Math.floor(main_width / max_col_count) - GDThumb.margin;

      GDThumb.big_thumb.height = thumb_width * 2 + GDThumb.margin;
      GDThumb.big_thumb.width = GDThumb.big_thumb.height;
      GDThumb.big_thumb.crop = GDThumb.big_thumb.height;
      GDThumb.max_height = thumb_width;
    } else if (GDThumb.method === "slide") {
      main_width = document.querySelector("ul.thumbnails").clientWidth;
      max_col_count = Math.floor(main_width / GDThumb.max_height);
      thumb_width = Math.floor(main_width / max_col_count) - GDThumb.margin;
      GDThumb.max_height = thumb_width;
    }

    GDThumb.t = []; // Using an array literal instead of new Array()

    document.querySelectorAll("ul.thumbnails img.thumbnail").forEach((img, index) => {
      var width = parseInt(img.getAttribute("width"));
      var height = parseInt(img.getAttribute("height"));
      var th = { index: index, width: width, height: height, real_width: width, real_height: height };

      if (GDThumb.check_pv) {
        var ratio = th.width / th.height;
        GDThumb.big_thumb_block = ratio > 2.2 || ratio < 0.455;
      }

      if ((GDThumb.method === "square" || GDThumb.method === "slide") && th.height !== th.width) {
        th.width = GDThumb.max_height;
        th.height = GDThumb.max_height;
        th.crop = GDThumb.max_height;
      } else if (height < GDThumb.max_height) {
        th.width = Math.round((GDThumb.max_height * width) / height);
        th.height = GDThumb.max_height;
      }

      GDThumb.t.push(th);
    });

    if (GDThumb.big_thumb_block) {
      GDThumb.big_thumb = null;
    }

    var first = GDThumb.t[0];
    if (first) {
      GDThumb.small_thumb = {
        index: first.index,
        width: first.real_width,
        height: first.real_height,
        src: document.querySelector("ul.thumbnails img.thumbnail:first-child").getAttribute("src"), // Using first-child selector
      };
    }

    GDThumb.process();
  },

  // Adjust thumb attributes to match plugin settings
  process: function () {
    console.log("process()");
    var width_count = GDThumb.margin;
    var line = 1;
    var round_rest = 0;
    var main_width = document.querySelector("ul.thumbnails").clientWidth; // Get width using native method
    var first_thumb = document.querySelector("ul.thumbnails img.thumbnail:first-child"); // Select first thumbnail
    var best_size = { width: 1, height: 1 };

    if (GDThumb.method === "slide") {
      best_size.width = GDThumb.max_height;
      best_size.height = GDThumb.max_height;

      GDThumb.resize(first_thumb, GDThumb.t[0].real_width, GDThumb.t[0].real_height, GDThumb.t[0].width, GDThumb.t[0].height, false);
    } else if (GDThumb.method === "square") {
      if (GDThumb.big_thumb !== null) {
        best_size.width = GDThumb.big_thumb.width;
        best_size.height = GDThumb.big_thumb.height;

        if (GDThumb.big_thumb.src !== first_thumb.src) {
          first_thumb.src = GDThumb.big_thumb.src; // Set source
          first_thumb.width = GDThumb.big_thumb.width; // Set width
          first_thumb.height = GDThumb.big_thumb.height; // Set height
          GDThumb.t[0].width = GDThumb.big_thumb.width;
          GDThumb.t[0].height = GDThumb.big_thumb.height;
        }
        GDThumb.t[0].crop = best_size.width;
        GDThumb.resize(first_thumb, GDThumb.t[0].real_width, GDThumb.t[0].real_height, GDThumb.big_thumb.width, GDThumb.big_thumb.height, true);
      } else {
        best_size.width = GDThumb.max_height;
        best_size.height = GDThumb.max_height;
        GDThumb.resize(first_thumb, GDThumb.t[0].real_width, GDThumb.t[0].real_height, GDThumb.t[0].width, GDThumb.t[0].height, true);
      }
    } else {
      if (GDThumb.big_thumb !== null && GDThumb.big_thumb.height < main_width * GDThumb.max_first_thumb_width) {
        // Compute best size for landscape picture (we choose bigger height)
        var min_ratio = Math.min(1.05, GDThumb.big_thumb.width / GDThumb.big_thumb.height);

        for (var width = GDThumb.big_thumb.width; width / best_size.height >= min_ratio; width--) {
          width_count = GDThumb.margin;
          var height = GDThumb.margin;
          var max_height = 0;
          var available_width = main_width - (width + GDThumb.margin);
          line = 1;
          for (var i = 1; i < GDThumb.t.length; i++) {
            width_count += GDThumb.t[i].width + GDThumb.margin;
            max_height = Math.max(GDThumb.t[i].height, max_height);

            if (width_count > available_width) {
              var ratio = width_count / available_width;
              height += Math.round(max_height / ratio);
              line++;
              max_height = 0;
              width_count = GDThumb.margin;
              if (line > 2) {
                if (height >= best_size.height && width / height >= min_ratio && height <= GDThumb.big_thumb.height) {
                  best_size = { width: width, height: height };
                }
                break;
              }
            }
          }
          if (line <= 2) {
            if (max_height === 0 || line === 1) {
              height = GDThumb.big_thumb.height;
            } else {
              height += max_height;
            }
            if (height >= best_size.height && width / height >= min_ratio && height <= GDThumb.big_thumb.height) {
              best_size = { width: width, height: height };
            }
          }
        }
        if (GDThumb.big_thumb.src !== first_thumb.src) {
          first_thumb.src = GDThumb.big_thumb.src;
          first_thumb.width = GDThumb.big_thumb.width;
          first_thumb.height = GDThumb.big_thumb.height;
          GDThumb.t[0].width = GDThumb.big_thumb.width;
          GDThumb.t[0].height = GDThumb.big_thumb.height;
        }
        GDThumb.t[0].crop = best_size.width;
        GDThumb.resize(first_thumb, GDThumb.big_thumb.width, GDThumb.big_thumb.height, best_size.width, best_size.height, true);
      }
    }

    if (best_size.width === 1) {
      if (GDThumb.small_thumb !== null && GDThumb.small_thumb.src !== first_thumb.src) {
        first_thumb.src = GDThumb.small_thumb.src;
        first_thumb.width = GDThumb.small_thumb.width;
        first_thumb.height = GDThumb.small_thumb.height;
        GDThumb.t[0].width = GDThumb.small_thumb.width;
        GDThumb.t[0].height = GDThumb.small_thumb.height;
      }
      GDThumb.t[0].crop = false;
    }

    width_count = GDThumb.margin;
    max_height = 0;
    var last_height = GDThumb.max_height;
    line = 1;
    var thumb_process = [];

    for (i = GDThumb.t[0].crop !== false ? 1 : 0; i < GDThumb.t.length; i++) {
      width_count += GDThumb.t[i].width + GDThumb.margin;
      max_height = Math.max(GDThumb.t[i].height, max_height);
      thumb_process.push(GDThumb.t[i]);

      available_width = main_width;
      if (line <= 2 && GDThumb.t[0].crop !== false) {
        available_width -= GDThumb.t[0].crop + GDThumb.margin;
      }

      if (width_count > available_width) {
        var last_thumb = GDThumb.t[i].index;
        ratio = width_count / available_width;
        var new_height = Math.round(max_height / ratio);
        round_rest = 0;
        width_count = GDThumb.margin;

        for (j = 0; j < thumb_process.length; j++) {
          if (GDThumb.method === "square" || GDThumb.method === "slide") {
            var new_width = GDThumb.max_height;
            new_height = GDThumb.max_height;
          } else {
            if (thumb_process[j].index === last_thumb) {
              new_width = available_width - width_count - GDThumb.margin;
            } else {
              new_width = (thumb_process[j].width + round_rest) / ratio;
              round_rest = new_width - Math.round(new_width);
              new_width = Math.round(new_width);
            }
          }
          GDThumb.resize(
            document.querySelectorAll("ul.thumbnails img.thumbnail")[thumb_process[j].index],
            thumb_process[j].real_width,
            thumb_process[j].real_height,
            new_width,
            new_height,
            false
          );
          last_height = Math.min(last_height, new_height);

          width_count += new_width + GDThumb.margin;
        }
        thumb_process = [];
        width_count = GDThumb.margin;
        max_height = 0;
        line++;
      }
    }

    if (last_height === 0) {
      last_height = GDThumb.max_height;
    }

    // Crop last line only if we have more than one line
    for (var j = 0; j < thumb_process.length; j++) {
      // we have only one line, i.e. the first line is the one and only line and therefor the last line too
      if (line === 1) {
        GDThumb.resize(
          document.querySelectorAll("ul.thumbnails img.thumbnail")[thumb_process[j].index],
          thumb_process[j].real_width,
          thumb_process[j].real_height,
          thumb_process[j].width,
          last_height,
          false
        );
      }
      // we have more than one line
      else {
        if (GDThumb.method === "square" || GDThumb.method === "slide") {
          new_width = GDThumb.max_height;
          new_height = GDThumb.max_height;
        } else {
          new_width = (thumb_process[j].width + round_rest) / ratio;
          round_rest = new_width - Math.round(new_width);
          new_width = Math.round(new_width);
        }

        GDThumb.resize(
          document.querySelectorAll("ul.thumbnails img.thumbnail")[thumb_process[j].index],
          thumb_process[j].real_width,
          thumb_process[j].real_height,
          new_width,
          new_height,
          false
        );
        last_height = Math.min(last_height, new_height);

        width_count += new_width + GDThumb.margin;
      }
    }

    if (main_width !== document.querySelector("ul.thumbnails").clientWidth) {
      GDThumb.process();
    }
  },

  resize: function (thumb, width, height, new_width, new_height, is_big) {
    var use_crop = true;
    if (GDThumb.method === "slide") {
      use_crop = false;
      thumb.style.height = "";
      thumb.style.width = "";
      new_width = new_height;

      var real_height, real_width;

      if (width < height) {
        real_height = Math.round((height * new_width) / width);
        real_width = new_width;
      } else {
        real_height = new_width;
        real_width = Math.round((width * new_height) / height);
      }

      var height_crop = Math.round((real_height - new_height) / 2);
      var width_crop = Math.round((real_width - new_height) / 2);

      thumb.style.height = real_height + "px";
      thumb.style.width = real_width + "px";
    } else if (!is_big && GDThumb.method === "square") {
      thumb.style.height = "";
      thumb.style.width = "";
      new_width = new_height;

      if (width < height) {
        real_height = Math.round((height * new_width) / width);
        real_width = new_width;
      } else {
        real_height = new_width;
        real_width = Math.round((width * new_height) / height);
      }

      height_crop = Math.round((real_height - new_height) / 2);
      width_crop = Math.round((real_width - new_width) / 2);

      thumb.style.height = real_height + "px";
      thumb.style.width = real_width + "px";
    } else if (GDThumb.method === "resize" || height < new_height || width < new_width) {
      real_width = new_width;
      real_height = new_height;
      width_crop = 0;
      height_crop = 0;

      if (is_big) {
        if (width - new_width > height - new_height) {
          real_width = Math.round((new_height * width) / height);
          width_crop = Math.round((real_width - new_width) / 2);
        } else {
          real_height = Math.round((new_width * height) / width);
          height_crop = Math.round((real_height - new_height) / 2);
        }
      }

      thumb.style.height = real_height + "px";
      thumb.style.width = real_width + "px";
    } else {
      thumb.style.height = "";
      thumb.style.width = "";
      height_crop = Math.round((height - new_height) / 2);
      width_crop = Math.round((width - new_width) / 2);
    }

    var parentLi = thumb.closest("li"); // Use closest to find the parent <li>
    parentLi.style.height = new_height + "px";
    parentLi.style.width = new_width + "px";

    var parentA = thumb.parentElement; // Get the parent <a>
    if (use_crop) {
      parentA.style.clip = `rect(${height_crop}px, ${new_width + width_crop}px, ${new_height + height_crop}px, ${width_crop}px)`;
      parentA.style.top = -height_crop + "px";
      parentA.style.left = -width_crop + "px";
    } else {
      parentA.style.top = -height_crop + "px";
      parentA.style.left = -width_crop + "px";
    }
  },
};
