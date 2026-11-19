# #0019 Combined Excel export bypasses formula-safe cell normalization

- 2026-10-28T20:31:13Z `issue`: Combined Excel export bypasses formula-safe cell normalization [app/Services/AdminDataExportService.php:403]
- 2026-10-28T20:54:29Z `fix`: Normalize formula-like combined-export cell values before workbook serialization; regression and full isolated backend suite pass. [app/Services/AdminDataExportService.php]
