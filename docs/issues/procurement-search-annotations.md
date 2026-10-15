# Procurement search annotations (2026-10-15)

## Evidence and cause

Browser comments report hidden office search results and an empty Particular search. Reproduced in the existing OfficeSearchFocused Storybook story: query MISS selects MAO-MISS, but the list retains scrollTop 612. The selected row is at y=-327 while the list begins at y=281. cmdk only scrolls on a changed selection; searching while the same option remains selected can retain an unrelated viewport. Its fuzzy matching also includes many unrelated office abbreviations through their full names.

Staging has zero active particulars. The presentation service correctly returns active rows only. The current generic 'No matches found' message hides that configuration state. No seed or SSO change is needed.

## Scoped fix

Update the private procurement SearchChoice composition in application and Storybook. Control the search query, reset list scroll when the query or open state changes, and match contiguous query tokens against labels and existing keywords. Keep cmdk keyboard navigation, option IDs, Popover focus handling, and SSO names. Distinguish no available options from a query with no matches. Preserve the existing primitives and workflows.

## Verification

Build/typecheck the app and Storybook. Browser regression: scroll office results, search MISS, verify the selected result is visible; select by keyboard and mouse; search full names; clear search; test no-match and empty catalog states. Verify Particular selection with existing Storybook fixtures. Check focus outline on the whole search wrapper. Login proposals are a separate review artifact.

## Result

Implemented the scoped fix. Added explicit Command accessible labels after regression testing exposed cmdk aria-labelledby precedence. Abbreviation/label substring matches rank ahead of full-name matches. Regression stories and full-form browser checks passed locally. The application build and 813 PHP tests passed. No data or SSO configuration was changed.
