(function () {
  var loader = new ImageLoader({ onChanged: loaderChanged, maxRequests: 1 });
  var pending_next_page = null;
  var allDoneDfd;
  var urlDfd;

  function createDeferred() {
    let resolve, reject;
    const promise = new Promise((res, rej) => {
      resolve = res;
      reject = rej;
    });
    promise.resolve = resolve;
    promise.reject = reject;
    return promise;
  }

  function gdThumb_start() {
    allDoneDfd = createDeferred();
    urlDfd = createDeferred();

    allDoneDfd.finally(function () {
      document.getElementById("startLink").removeAttribute("disabled");
      document.getElementById("startLink").style.opacity = 1;
      document.querySelectorAll("#pauseLink, #stopLink").forEach(function (element) {
        element.setAttribute("disabled", true);
        element.style.opacity = 0.5;
      });
    });

    urlDfd.finally(function () {
      if (loader.remaining() == 0) allDoneDfd.resolve();
    });

    setTimeout(() => {
      document.getElementById("generate_cache").style.display = "block";
      document.getElementById("startLink").setAttribute("disabled", true);
      document.getElementById("startLink").style.opacity = 0.5;
      document.getElementById("pauseLink").removeAttribute("disabled");
      document.getElementById("pauseLink").style.opacity = 1;
      document.getElementById("stopLink").removeAttribute("disabled");
      document.getElementById("stopLink").style.opacity = 1;
    }, 0);

    loader.pause(false);
    updateStats();
    getUrls(0);
  }

  function gdThumb_pause() {
    loader.pause(!loader.pause());
  }

  function gdThumb_stop() {
    loader.clear();
    urlDfd.resolve();
  }

  function getUrls(page_token) {
    var data = { prev_page: page_token, max_urls: 500, types: [] };
    const urlParams = new URLSearchParams();
    for (const key in data) {
      if (Array.isArray(data[key])) {
        // If the value is an array, you can convert it to a JSON string or handle it as needed
        urlParams.append(key, JSON.stringify(data[key]));
      } else {
        urlParams.append(key, data[key]);
      }
    }
    const urlEncodedString = urlParams.toString();

    fetch("admin.php?page=plugin-GDThumb&getMissingDerivative=", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: urlEncodedString,
    })
      .then((response) => {
        if (!response.ok) {
          // Attempt to extract custom error message from the response
          return response.json().then((errorData) => {
            throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
          });
        }
        return response.json();
      })
      .then(wsData)
      .catch(wsError);
  }

  function wsData(data) {
    loader.add(data.urls);
    if (data.next_page) {
      if (loader.pause() || loader.remaining() > 100) {
        pending_next_page = data.next_page;
      } else {
        getUrls(data.next_page);
      }
    }
  }

  function wsError(error) {
    console.error("Error details:", error);
    urlDfd.reject();
  }

  function updateStats() {
    document.getElementById("loaded").textContent = loader.loaded;
    document.getElementById("errors").textContent = loader.errors;
    document.getElementById("remaining").textContent = loader.remaining();

    if (loader.remaining() == 0) {
      let startLink = document.getElementById("startLink");
      startLink.disabled = false;
      startLink.style.opacity = 1;

      let pauseLink = document.getElementById("pauseLink");
      pauseLink.disabled = true;
      pauseLink.style.opacity = 0.5;

      let stopLink = document.getElementById("stopLink");
      stopLink.disabled = true;
      stopLink.style.opacity = 0.5;
    }
  }

  function loaderChanged() {
    updateStats();

    if (pending_next_page && 100 > loader.remaining()) {
      getUrls(pending_next_page);
      pending_next_page = null;
    } else if (loader.remaining() === 0 && urlDfd) {
      // Assuming urlDfd is a Promise, check if it is resolved or rejected
      urlDfd
        .then(() => {
          allDoneDfd.resolve();
        })
        .catch(() => {
          allDoneDfd.resolve();
        });
    }
  }

  // Assign functions to the global scope if needed
  window.gdThumb_start = gdThumb_start;
  window.gdThumb_pause = gdThumb_pause;
  window.gdThumb_stop = gdThumb_stop;
})();
