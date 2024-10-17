# YSE Embedded Paragraphs Adopter

This module reclaims embedded paragraphs in text_long and text_w_summary fields in node fields and entity reference revisions fields for a node.   The fields are filtered using entity_embed logic, grabs paragraphs and appends additions to a hidden field on the node.  This prevents embedded paragraphs from being 'orphaned' and exposed to deletion from other processes.

This depends on a configurable (not base) field being added to each node type that opts-in through a third-party setting, as well as a text profile that includes the button from paragraphs_inline_entity_form.

There are manual configuration efforts!
- To limit the paragraphs available to the embed.
- Each node type that you want to support embeds has a third-party setting in the tabs on the node type form.

## Dependency

drupal/paragraphs_inline_entity_form

## TODO
- really needs a check on the ckeditor side to remove or disable the paragraphs embed button if the supporting text format is used, but the node type has not opted-in.

