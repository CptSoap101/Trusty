# Connect Instagram to Trusty Ofisina

This lets the Hostinger version post the "Pabien!" photo straight to @trusty_autorijschool. You only set it up once. It takes about 20 minutes.

You need:

- @trusty_autorijschool as a **professional account** (Business or Creator), linked to the **Trusty Facebook page**.
- Admin access to that Facebook page.

Meta changes its menus often. If a button has a slightly different name, look for the closest match.

## 1. Make a Meta app

1. Go to **developers.facebook.com** and log in with the Facebook account that manages the Trusty page.
2. Click **My Apps → Create app**.
3. Choose the use case for managing **Instagram** content (or choose **Other → Business** if asked for an app type).
4. Name it, for example `Trusty Ofisina`, and link it to the Trusty **business portfolio** if you have one.
5. In the app, add the product **Instagram** (the version that works with **Facebook Login**).

The app can stay in **Development** mode. You post to your own account, so it doesn't need Meta's review.

## 2. Make a token that doesn't expire (recommended)

1. Go to **business.facebook.com → Settings → Users → System users**.
2. Click **Add**, name it `Trusty Ofisina`, and give it the **Admin** role.
3. Click **Assign assets** and give it full control of:
   - the **Trusty Facebook page**
   - the **@trusty_autorijschool** Instagram account
   - the **Trusty Ofisina** app
4. Click **Generate new token**, pick the Trusty Ofisina app, choose **Never** for expiry if asked, and tick these permissions:
   - `instagram_basic`
   - `instagram_content_publish`
   - `pages_show_list`
   - `pages_read_engagement`
   - `business_management`
5. Copy the token. It's a long code starting with `EAA`.

No business portfolio? You can make a token in **Graph API Explorer** on developers.facebook.com with the same permissions. That token expires after about 60 days, after which you connect again with a new one.

## 3. Connect it in the app

1. Open the Hostinger version and go to **Settings → Connect Instagram**.
2. Paste the token and click **Connect**.
3. The app finds @trusty_autorijschool by itself. If you manage more than one account, pick the right one.

It now says **Instagram connected**. The token is stored only on your server, in the protected `data` folder. It's never shown in the app and never sent to GitHub.

## 4. Post

1. When a client passes, set their exam result to **Passed** on their page.
2. Click **Upload photo** in the window that opens.
3. The app places the photo in your template, prints the name and fills in the caption.
4. Click **Review and post to Instagram**. You see the finished image and caption, and can still change the caption.
5. Click **Post now**. After a few seconds you get a link to the post, and the client is marked as posted.

## If something goes wrong

| Message | What to do |
|---|---|
| "No Instagram professional account was found" | Check that the token has all five permissions and that the Instagram account is linked to the Facebook page (Instagram → Settings → Account type and tools). |
| "The Instagram token has expired" | Make a new token (step 2) and connect again. |
| "Instagram couldn't process the image" | Try again. If it keeps failing, check that HTTPS is on for your subdomain; Instagram has to be able to download the image from your site. |
| Anything starting with "Instagram said:" | That's Meta's own message. It usually names the missing permission or setting. |

Facebook posting isn't included yet; it can be added the same way.
