(function (blocks, element, blockEditor, i18n) {
  var el = element.createElement;
  var __ = i18n.__;

  blocks.registerBlockType("ecm/event-countdown", {
    title: __("Event Countdown", "event-countdown-manager"),
    icon: "clock",
    category: "widgets",
    attributes: {
      eventId: { type: "number", default: 0 },
    },
    edit: function (props) {
      return el(
        "div",
        { className: props.className },
        el("p", {}, __("Render one event countdown by ID.", "event-countdown-manager")),
        el(blockEditor.InspectorControls, {},
          el("div", { style: { padding: "12px" } },
            el("label", {}, __("Event ID", "event-countdown-manager")),
            el("input", {
              type: "number",
              min: 0,
              value: props.attributes.eventId,
              onChange: function (e) {
                props.setAttributes({ eventId: parseInt(e.target.value || "0", 10) });
              },
              style: { width: "100%" },
            })
          )
        ),
        el("code", {}, "[ecm_event_countdown event_id=\"" + (props.attributes.eventId || 0) + "\"]")
      );
    },
    save: function () {
      return null;
    },
  });

  blocks.registerBlockType("ecm/upcoming-events", {
    title: __("Upcoming Events", "event-countdown-manager"),
    icon: "calendar-alt",
    category: "widgets",
    attributes: {
      limit: { type: "number", default: 5 },
    },
    edit: function (props) {
      return el(
        "div",
        { className: props.className },
        el("p", {}, __("Render upcoming event countdowns.", "event-countdown-manager")),
        el(blockEditor.InspectorControls, {},
          el("div", { style: { padding: "12px" } },
            el("label", {}, __("Limit", "event-countdown-manager")),
            el("input", {
              type: "number",
              min: 1,
              max: 20,
              value: props.attributes.limit,
              onChange: function (e) {
                props.setAttributes({ limit: parseInt(e.target.value || "5", 10) });
              },
              style: { width: "100%" },
            })
          )
        ),
        el("code", {}, "[ecm_upcoming_events limit=\"" + (props.attributes.limit || 5) + "\"]")
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n);
