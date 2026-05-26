function transformOxygenSelectorProperties(props) {
  var PATH_MAP = {
    "typography.typography.custom.customTypography.fontSize": "typography.font_size",
    "layout.align_items": "layout.flex_align.cross_axis",
    "layout.flex_wrap": "__flex_wrap__",
    "background.color": "background.background_color",
    "spacing.padding": "spacing.spacing.padding"
  };
  return props;
}
