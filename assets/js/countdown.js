(function () {
  function pad(value) {
    return String(value).padStart(2, "0");
  }

  function tick(card) {
    var target = card.getAttribute("data-ecm-countdown");
    if (!target) {
      return;
    }

    var endTime = new Date(target).getTime();
    if (Number.isNaN(endTime)) {
      return;
    }

    var now = Date.now();
    var diff = Math.max(0, Math.floor((endTime - now) / 1000));

    var days = Math.floor(diff / 86400);
    var hours = Math.floor((diff % 86400) / 3600);
    var minutes = Math.floor((diff % 3600) / 60);
    var seconds = diff % 60;

    var daysNode = card.querySelector("[data-ecm-days]");
    var hoursNode = card.querySelector("[data-ecm-hours]");
    var minutesNode = card.querySelector("[data-ecm-minutes]");
    var secondsNode = card.querySelector("[data-ecm-seconds]");

    if (daysNode) daysNode.textContent = pad(days);
    if (hoursNode) hoursNode.textContent = pad(hours);
    if (minutesNode) minutesNode.textContent = pad(minutes);
    if (secondsNode) secondsNode.textContent = pad(seconds);
  }

  function init() {
    var cards = document.querySelectorAll("[data-ecm-countdown]");
    cards.forEach(function (card) {
      tick(card);
      window.setInterval(function () {
        tick(card);
      }, 1000);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
