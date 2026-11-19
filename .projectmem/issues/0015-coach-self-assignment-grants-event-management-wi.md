# #0015 Coach self-assignment grants event management without prior event access

- 2026-10-28T20:31:13Z `issue`: Coach self-assignment grants event management without prior event access [routes/web.php:157]
- 2026-10-28T20:54:17Z `fix`: Require existing event-manager assignment before a coach can add their own event coach registration; authorization regression and full isolated backend suite pass. [app/Http/Controllers/ChampionshipController.php]
