# YSE Embedded Paragraphs Adopter

## Managing embedded paragraphs

This module reclaims embedded paragraphs in text_long and text_w_summary fields in node fields and entity reference revisions fields for a node.   The fields are filtered using entity_embed logic, grabs paragraphs and appends additions to a hidden field on the node.  This prevents embedded paragraphs from being 'orphaned' and exposed to deletion from other processes.

This depends on a configurable (not base) field being added to each node type that opts-in through a third-party setting, as well as a text profile that includes the button from paragraphs_inline_entity_form.

There are manual configuration efforts!

- To limit the paragraphs available to the embed.
- Each node type that you want to support embeds has a third-party setting in the tabs on the node type form.

## Paragraphs Behaviors Tabs

The js that looks for the 'Content' and 'Behavior' tabs depends on having the tabs rendered in the right part of the DOM relative to the paragraph form.  When Paragraphs Inline Entity Form is used, there are two different form presentations.  The first is the entity browser new inline entity form, based on the 'paragraphs_items' entity browser configuration.  The second is the entity browser edit route, that retrieves and renders the paragraph edit form, in a modal, not using an inline entity form.

The javascript that looks for the tabs looks for the top-most set of tabs in a field widget, accounting for nesting etc.  This presents a challenge because the embed edit action does not use a field, and the modal covers up the tabs in the parent form.

To account for the missing field widget structure, this module creates or reuses a container that surrounds the fields within the form, with attributes intended to match the paragraphs.admin.js tab logic.  We also standardize on an iframe delivery for both forms, to simplify the DOM that is searched.  We use hook_theme and twig templates to place the top-most set of tabs.

## Dependency

drupal/paragraphs_inline_entity_form

## TODO

- really needs a check on the ckeditor side to remove or disable the paragraphs embed button if the supporting text format is used, but the node type has not opted-in.   But access is based on route not other contexts
