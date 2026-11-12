# PromiseEdger (v2)

Gold & black theme, light/dark toggle, English / اردو (RTL).

## Setup
1. XAMPP/MySQL start karo. DB password `config/config.php` me hai (ya `config/config.local.php` banakar override karo).
2. **Naya install:** `db.sql` import karo, phir (optional) `seed.sql` demo data ke liye.
3. **Purana database hai?** Kuch mat karo. Pehli request par app khud DB upgrade kar leti hai (`config/schema.php`):
   naye tables/columns banti hain, purane `likes` -> `reactions` me copy hote hain, purane users verified mark hote hain.
4. Pehla registered user (khali DB par) khud **admin** banta hai. Warna: `UPDATE users SET role='admin' WHERE email='...';`

## Email (verification + forgot password)
`config/config.php` me `MAIL_DRIVER`:
- `log` (default): email bheji nahi jati, `storage/mail.log` me likhi jati hai aur local testing me link screen par dikhta hai.
- `smtp`: Gmail ke liye `MAIL_HOST=smtp.gmail.com`, port 587, `tls`, `MAIL_USER`, `MAIL_PASS` (Google App Password).
- `mail`: hosting ka PHP mail().
Live server par `APP_DEBUG` ko `false` karo. Email verification band karni ho to `REQUIRE_VERIFIED_EMAIL=false`.

## Features
Status (In progress / Done / Missed) + progress bar - deadline + "3 days left" badge + daily reminder notification - categories + filter -
daily check-in streaks + badges - follow + Following feed - notifications (reaction, comment, follow, reminder, streak) -
report + admin review - leaderboard (most kept / top streaks) - emoji reactions - forgot password + email verification -
admin analytics charts (7/30/90 days).

## Files
`partials/promise_card.php` (card design), `style.css` (design + animations), `app.js` (client logic), `charts.js` (admin charts),
`config/translations.php` (EN + UR text), `config/schema.php` (auto DB upgrade), `config/mailer.php`.
Note: `check.php` (temporary diagnostics) aur `like.php` (reactions ne replace kiya) hata diye gaye hain.
