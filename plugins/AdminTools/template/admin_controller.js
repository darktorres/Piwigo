// import "../../../node_modules/jquery/dist/jquery.js";
// import "https://raw.githack.com/drewwilson/TipTip/refs/heads/master/jquery.tipTip.js";

let urlWS;
let urlSelf;
let multiView;

const $ato = $("#ato_container");

// fill multiview selects
// data came from AJAX request or sessionStorage
function populateMultiView() {
  const $multiview = $ato.find(".multiview");

  if ($multiview.data("init")) return;

  const render = function (data) {
    let html = "";
    $.each(data.users, function (i, user) {
      if (user.status == "webmaster" || user.status == "admin") {
        html += `<option value="${user.id}">${user.username}</option>`;
      }
    });
    $multiview.find('select[data-type="view_as"]').html(html).val(multiView.view_as);

    html = "";
    $.each(["clear", "roma"], function (i, theme) {
      html += `<option value="${theme}">${theme}</option>`;
    });
    $multiview.find('select[data-type="theme"]').html(html).val(multiView.theme);

    html = "";
    $.each(data.languages, function (i, language) {
      html += `<option value="${language.id}">${language.name}</option>`;
    });
    $multiview.find('select[data-type="lang"]').html(html).val(multiView.lang);

    $multiview.data("init", true);
    $multiview.find(".switcher").show();
  };

  if ("sessionStorage" in window && window.sessionStorage.multiView !== undefined) {
    render(JSON.parse(window.sessionStorage.multiView));
  } else {
    $.ajax({
      method: "POST",
      url: `${urlWS}multiView.getData`,
      dataType: "json",
      success: function (data) {
        render(data.result);
        if ("sessionStorage" in window) {
          window.sessionStorage.multiView = JSON.stringify(data.result);
        }
      },
      error: function (xhr, text, error) {
        alert(`${text} ${error}`);
      },
    });
  }
}

// Delete session cache
function deleteCache() {
  if ("sessionStorage" in window) {
    window.sessionStorage.removeItem("multiView");
  }
}

// attach jquery handlers
function init(open) {
  $(".multiview").appendTo($ato);

  /* <!-- sub menus --> */
  $ato.on({
    click: function (e) {
      populateMultiView();
      $(this).find("ul").toggle();
    },
    mouseleave: function (e) {
      if (e.target.tagName.toLowerCase() !== "select") {
        $(this).find("ul").hide();
      }
    },
  });

  $ato.find(">a").on("click", function (e) {
    e.preventDefault();
  });

  $ato.find("ul").on("mouseleave", function (e) {
    if (e.target.tagName.toLowerCase() !== "select") {
      $(this).hide();
    }
  });

  /* <!-- select boxes --> */
  $ato.find(".switcher").on({
    change: function () {
      if ($(this).data("type") === "theme") {
        if ($(this).val() !== multiView.theme) {
          window.location.href = `${urlSelf}change_theme=1`;
        }
      } else {
        window.location.href = `${urlSelf}ato_${$(this).data("type")}=${$(this).val()}`;
      }
    },
    click: function (e) {
      e.stopPropagation();
    },
  });
}

// Setter functions
function setUrlWS(value) {
  urlWS = value;
}

function setUrlSelf(value) {
  urlSelf = value;
}

function setMultiView(value) {
  multiView = value;
}

// Export the module
export const AdminTools = {
  init,
  deleteCache,
  setUrlWS,
  setUrlSelf,
  setMultiView,
};
