(function () {
  const loader = new ImageLoader({ onChanged: loaderChanged, maxRequests: 1 });
  let pendingNextPage = null;
  let allDoneDfd;
  let urlDfd;

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

  function enableElement(element, isEnabled) {
    element.disabled = !isEnabled;
    element.style.opacity = isEnabled ? 1 : 0.5;
  }

  function gdThumbStart() {
    allDoneDfd = createDeferred();
    urlDfd = createDeferred();

    allDoneDfd.finally(() => {
      enableElement(document.getElementById("startLink"), true);
      document.querySelectorAll("#pauseLink, #stopLink").forEach((element) => enableElement(element, false));
    });

    urlDfd.finally(() => {
      if (loader.remaining() === 0) {
        allDoneDfd.resolve();
      }
    });

    setTimeout(() => {
      document.getElementById("generate_cache").style.display = "block";
      enableElement(document.getElementById("startLink"), false);
      enableElement(document.getElementById("pauseLink"), true);
      enableElement(document.getElementById("stopLink"), true);
    }, 0);

    loader.pause(false);
    updateStats();
    getUrls(0);
  }

  function gdThumbPause() {
    loader.pause(!loader.pause());
  }

  function gdThumbStop() {
    loader.clear();
    urlDfd.resolve();
  }

  async function getUrls(pageToken) {
    const data = { prev_page: pageToken, max_urls: 500, types: [] };
    const urlParams = new URLSearchParams(data);

    try {
      const response = await fetch("admin.php?page=plugin-GDThumb&getMissingDerivative=", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: urlParams.toString(),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
      }

      const result = await response.json();
      wsData(result);
    } catch (error) {
      wsError(error);
    }
  }

  function wsData(data) {
    loader.add(data.urls);
    if (data.next_page) {
      if (loader.pause() || loader.remaining() > 100) {
        pendingNextPage = data.next_page;
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

    if (loader.remaining() === 0) {
      enableElement(document.getElementById("startLink"), true);
      enableElement(document.getElementById("pauseLink"), false);
      enableElement(document.getElementById("stopLink"), false);
    }
  }

  function loaderChanged() {
    updateStats();

    if (pendingNextPage && loader.remaining() < 100) {
      getUrls(pendingNextPage);
      pendingNextPage = null;
    } else if (loader.remaining() === 0 && urlDfd) {
      urlDfd.then(allDoneDfd.resolve).catch(allDoneDfd.resolve);
    }
  }

  // Assign functions to the global scope if needed
  window.gdThumbStart = gdThumbStart;
  window.gdThumbPause = gdThumbPause;
  window.gdThumbStop = gdThumbStop;
})();
