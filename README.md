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

## Where it runs today

The app is published as a Claude artifact. Claude provides the shared database, photo storage and file downloads. Opened as a plain file or on another host, the app shows its design but can't save data.

A Hostinger version with its own database, uploads folder and logins is planned. It will replace the Claude-specific parts (`claude.use("db")`, `claude.use("assets")`, `claude.use("downloads")`) and keep the same design and features.

No client data is stored in this repository; it lives only in the app's database.
