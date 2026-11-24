# Trusty Ofisina

The team workspace for **Trusty Autorijschool**, built for the instructors (Raichel and Raidjir) to replace paperwork.

## What it does

- **Clients and packages:** add a client, pick the vehicle (Hyundai Accent automatic, Nissan Versa manual, or motorcycle) and the package. An invoice for the full package is created automatically.
- **Payments:** record what a client has paid. Every payment creates a numbered receipt, and the open balance is always visible. Invoices and receipts are saved per client and can be downloaded as PDF.
- **Lessons:** schedule practical (praktiko) and theory (teoria) lessons, assign Raichel or Raidjir, and send WhatsApp reminders. Theory classes can hold several clients and don't count against the package lessons.
- **Schedule:** a day and week view with exam days. Each day can be downloaded as an image or PDF, or copied as text.
- **ID printed:** track whether a client's ID has been printed for the exam date.
- **Expenses:** upload a photo of a receipt and it's filed by month and category.
- **Finance:** monthly omzet and omzetbelasting (OB), expenses, and a year overview for the inkomstenbelasting. Exports as CSV and PDF.
- **Social posts:** place a client's photo in the post template with the preset caption for @trusty_autorijschool.

## Files

| Path | What it is |
|---|---|
| `index.html` | The whole app: layout, styling and logic in one file |
| `assets/trusty-logo.png` | Trusty logo with a transparent background |
| `assets/icon-512.png`, `assets/icon-192.png`, `assets/apple-touch-icon.png` | App icons for the home screen |
| `hostinger/` | Server side for Hostinger: PHP API, login, one-time setup, and `shim.js`, which connects the app to the server |
| `build-hostinger.sh` | Builds `dist/trusty-ofisina-hostinger.zip`, ready to upload |
| `HOSTINGER.md` | Step-by-step install guide for Hostinger |

## Where it runs

**Claude:** published as a Claude artifact. Claude provides the shared database, photo storage and file downloads.

**Hostinger:** the same `index.html` runs on PHP and MySQL. `hostinger/shim.js` provides the database, uploads and downloads that Claude provides otherwise, so the app code is shared. Build the upload package with:

```bash
./build-hostinger.sh
```

Then follow [HOSTINGER.md](HOSTINGER.md). Move data between the two with **Settings → Backup**.

No client data, passwords or database details are stored in this repository. `config.php` (created on the server by `setup.php`) is ignored by git.
