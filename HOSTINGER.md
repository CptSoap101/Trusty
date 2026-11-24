# Put Trusty Ofisina on Hostinger

This takes about 15 minutes. You only do it once.

## 1. Make a subdomain (optional, recommended)

In **hPanel → Domains → Subdomains**, create for example `ofisina` on your domain, so the app opens at `ofisina.yourdomain.com`. Hostinger creates a folder for it, usually `public_html/ofisina`.

You can also use a folder on your main site instead, for example `yourdomain.com/ofisina`.

## 2. Create a database

1. Go to **hPanel → Databases → MySQL Databases**.
2. Create a new database and a new user for it, with a strong password.
3. Write down the **database name**, **user** and **password**. They start with `u…_`.

Use a new database just for this app, not the one your website uses.

## 3. Upload the files

1. Open **hPanel → Files → File Manager** and go to the subdomain's folder (for example `public_html/ofisina`).
2. Click **Upload** and choose `trusty-ofisina-hostinger.zip`.
3. Right-click the zip and choose **Extract**. Extract it into this same folder.
4. Delete the zip file afterwards.

The folder should now contain `index.php`, `api.php`, `app.html`, `setup.php`, an `assets` folder and a `data` folder, among other files.

## 4. Turn on HTTPS

In **hPanel → Security → SSL**, install the free SSL certificate for the subdomain, and turn on **Force HTTPS**. Your clients' details then always travel encrypted.

## 5. Run the setup

1. Open `https://ofisina.yourdomain.com/setup.php` in your browser.
2. **Step 1:** fill in the database name, user and password from step 2. The host is `localhost`.
3. **Step 2:** choose a password for Raichel and one for Raidjir, at least 10 characters each.
4. When it says **All set**, go back to File Manager and **delete `setup.php`**.

Do the setup right after uploading. Until it's done, anyone who knows the address could run it.

## 6. Log in and move your data

1. Open `https://ofisina.yourdomain.com` and log in.
2. In the **Claude** version of the app, go to **Settings → Backup → Download backup**.
3. In the **Hostinger** version, go to **Settings → Backup → Restore from backup** and pick that file.

Photos (receipts, client photos and the social template) aren't in the backup file. Upload them again where you need them.

## 7. Add it to your phone

- **iPhone (Safari):** open the address, tap **Share → Add to Home Screen**.
- **Android (Chrome):** open the address, tap **⋮ → Add to Home screen** or **Install app**.

It opens full screen with the Trusty icon.

## Keeping it safe

- Turn on **two-factor login** for your Hostinger account.
- Download a backup from **Settings → Backup** every week.
- Keep your other websites on this Hostinger account up to date.
- Uploaded photos are stored in the `data` folder, which browsers can't open directly; only logged-in users can see them through the app.

## Updating later

When the app changes, upload the new `app.html` (and any other changed files) over the old ones. Never overwrite `config.php` or the `data` folder: they hold your database connection and your uploaded photos.
