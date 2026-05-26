function transformOxygenSelectorProperties(props) {
  // Fixture intentionally returns props unchanged. The extractor under test only
  // parses the PATH_MAP literal below; it does not exercise the transformation.
  var PATH_MAP = {
    "typography.typography.custom.customTypography.fontSize": "typography.font_size",
    "layout.align_items": "layout.flex_align.cross_axis",
    "layout.flex_wrap": "__flex_wrap__",
    "background.color": "background.background_color",
    "spacing.padding": "spacing.spacing.padding"
  };
  return props;
}
