# YSE Embedded Paragraphs Adopter

This module reclaims embedded paragraphs in text_long and text_w_summary fields in node fields and entity reference revisions fields for a node.   The fields are filtered using entity_embed logic, grabs paragraphs and appends additions to a hidden field on the node.  This prevents embedded paragraphs from being 'orphaned' and exposed to deletion from other processes.
