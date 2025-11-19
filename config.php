<?php

return array (
 // Dane z Graph Api Explorer w Developer Facebook w aplikacji CappellaMarialisTestApp
  'facebook_page_id' => '887291941135055',
  'facebook_app_id' => '1519385729118488',
  'facebook_app_secret' => '5b7da1f2cbbd8f2c3875f915c5b9ffcb',

  // generowany automarycznie
  'facebook_access_token' => 'EAAVl346pMRgBP8RltZC7gYWGRayWDMHoSKr9OMU1RJZC3OXR1HSC60hP8ZBsp7iN6kcZCY6g8nyp5mModZC4wklK6t56A1OD1hEqR5homVan4crGuT1Rr9Ik8bcEdKPCUajZCUezn6pRLo3R1c6rZAKN0R8VbJ7V4rnHychicFDOvs2O71QjcZCoZCtaXU6CJImpycMuQ',
  
  // Ile najnowszych postów wyświetla na stronie
  'posts_limit' => 3,

  // logowanie do odswiezenia tokenu:
  // nazwa-domeny/fb-login.php?s="admin_secret"
  'admin_secret' => 'jakas-losowa-mocna-frazka-13@',

// 
// 
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Można zmienić age hours do testów
// 1 to godzina
// 0.016 to minuta
// 
// Ile godzin cache ma być uznany za "świeży"
  'cache_refresh_hours' => 0.25, // czas ważności cache, 0.25 = 15 min
);
