# Photowall

Collects original iPhone photos from the public for research projects, checks every photo on our own
server, and gives the client a portal to download the approved originals.

It is a standalone PHP + MySQL app with its own database, logins and storage. It shares nothing with the
Reward Hours site.

| Address | Who | What |
| --- | --- | --- |
| photowall.rewardhours.com | Respondents | Terms, email code sign-in, basic details, upload |
| console.photowall.rewardhours.com | Staff | Projects, photo review, respondents, clients, downloads |
| client.photowall.researchandmetric.com | Clients | Approved photos of their own projects, downloads |

## What happens to an upload

1. The person opens the project link, agrees to the terms, confirms their email with a 6-digit code and
   fills in basic details. Coming back later needs only the same email and a new code.
2. Each file is checked in this order. A file that fails is deleted at once and the person is told why.
   - Limits: project open, closing date, photos per person, photos per day.
   - Exact duplicate (same file already in the project).
   - Complete file (HEIC cut short, JPEG that cannot be decoded).
   - Device: the photo's own camera information must say Apple and an accepted iPhone (16 or newer by
     default, plus named models such as iPhone Air and iPhone Duo).
   - Original: no editing software marks, system version that fits the phone, lens details and the
     hidden Apple camera notes present (strict mode), not resized or cropped, at least 8 megapixels.
   - Quality: not blurry, not too dark, not washed out.
   - Near duplicate (same picture saved again, also across different people).
   - Safety scan with two models that run locally. A high score discards the photo, a middle score
     keeps it but shows it blurred to staff with a warning.
3. Accepted photos wait for a member of staff. Nothing reaches the client before approval.
4. The original file is stored byte for byte as uploaded. Previews are separate JPEG copies. Downloads
   (single file or ZIP) return the untouched original, so all metadata is intact.

Photos per person and photos per day can each be set to 0, which means no limit.

Everyone who joins gets a contributor ID such as RM-C758947, and every kept photo an image ID (RM-I00001).
Contributor IDs are six digits and do not run in order, so an ID does not show how many people joined before;
each is worked out from the person's row number (`contributor_id()` in `app/lib/helpers.php`), so it never
changes and never repeats.

A project can have a target number of photos (Settings, Limits). Photos that are accepted or waiting for
review count. When the target is reached the project closes by itself: the upload page says it has collected
all the photos it needs, and new uploads are refused. The overview shows the progress towards the target.

The upload page is built for adding many photos at once. The contributor picks any number of photos and each
is uploaded and checked in turn, with a running total. Every photo has a category: one of the project's "What
to photograph" lines, or Other. By default the scanner suggests it (`scanner/categorize.py`): a face detector
and a general image classifier, both running on this server, sort a picture into portrait, landscape, indoor,
animal, food, still life or macro, which is then matched to the project's own wording. It is a best guess, not
a certainty: the contributor can change it per photo while it waits for review, staff can change it on the
photo's page, and the tracker marks categories that were detected and not yet checked by a person. The two
model files are downloaded by the installer; without them photos are filed under Other. On a phone the page
works like an app, with tabs along the bottom for Upload, Photos, Reward and Account.

When staff accept photos, the contributor gets an email listing them (image ID, file name, category, date),
their totals, the reward earned so far and their contributor ID. `bin/notify.php` runs every 10 minutes from
cron and sends one email per contributor, once staff have stopped approving that person's photos for five
minutes, so a review session gives one email and not one per photo. The contributor's upload page shows the same
details for every photo. The code is in `app/lib/Notify.php`.

Every page of a project in the console has the same tabs: Overview, Review photos, Tracker, Contributors, Turned
away and Settings. The overview leads with what needs doing (photos waiting for review), then the numbers, photos
by category, why uploads were turned away, the link to share and the downloads. Contributors can be searched by
name, email or contributor ID, filtered and ordered.

The console has a Tracker for each project (one of its tabs): one line for every photo that reached
the project, with image ID, file name, category, iPhone model, date taken, country, contributor ID and status
(Accepted, In review, or Rejected with the reason and whether staff or an automatic check rejected it). Uploads
that failed a check are listed with an RM-X ID, because the file itself is not kept. Below it are totals by
category and by contributor. All three tables download as CSV. The code is in `app/lib/Tracker.php`.

Each project can pay a reward: an amount for every so many accepted photos (set in the project's Settings under
Limits; new projects start with no photo limit and $1 for every 5). A photo counts once staff have approved it.
The reward is shown on the project page and the upload page, and the console's Respondents list shows what each
person has earned. Photowall does not make payments itself.

The hero of the public project page shows a phone on the right for each kind of photo the project asks for
(its "What to photograph" lines), sliding from one to the next. The phone itself is drawn by the page. An
example photo saved in the console under Settings, "Photos inside the phones", fills that phone's screen;
without one the phone shows an icon. Photos are kept per line of text, so projects that ask for the same kind
of photo share the example. Uploaded photos are turned upright and reduced to 1200 px tall.

The example photos can also be created with Google's Gemini API, in the console under Settings, "Create the
photos with Gemini". Staff paste their own Gemini API key (stored encrypted), choose the kind of photo, and
adjust the description, which is sent to Google as written with `{subject}` replaced by the kind of photo.
The result is kept as a draft and only appears on the project page after "Use this picture". "Create all
photos" makes one picture per kind in turn (skipping those that already have a draft), and "Use all pictures"
accepts every draft together. The model name (default `gemini-3.1-flash-image`) can be changed there if Google
renames it. The code is in `app/lib/Gemini.php`.

## Client portal: logo, activity, download approval

The client portal (`hosts.client`) shows the Research and Metric logo (`public/assets/rm-logo.png`) on a white
header. Clients only ever see accepted photos.

- **Activity.** Every sign-in (time, IP address, country when an IPzentra key is saved, browser), gallery page,
  photo viewed, download and request is written to `client_events`, with one line per photo in
  `client_photo_log`. Staff read it in the console under Clients, Activity, and can download it as CSV.
- **Download approval** (project Settings, off by default). When on, clients ask instead of downloading.
  Administrators get an email and answer under Clients, Download requests. An approved download works for
  7 days for the person who asked (`ClientLog::VALID_DAYS`), and can be withdrawn.
- **Bulk download** and **bulk upload** (project Settings, on by default). Off means clients take one photo at
  a time, and contributors add one photo at a time.

The code is in `app/lib/ClientLog.php`, `app/areas/client.php` and the `/clients/...` routes of
`app/areas/admin.php`.

## Clients in the console

`/clients` lists the client companies; `/clients/{id}` is one client's page with its people, linked projects,
latest activity and a "Manage this client" box. A client can be renamed, switched off (nobody from it can sign
in; nothing is deleted) or deleted (the company, its people, their download requests and activity go; its
projects and photos stay, without a client). Each person can be renamed, given a new email, switched off or
removed. Database change 011 adds `clients.active`.

## Visits

Pages of the contributor site and the client portal are counted on our own server, without cookies and without
any outside service (`app/lib/Visits.php`, table `visits`, console page `/visits`). One line is written per page
shown. A visitor is one browser on one day: a code made from the date, the connection address and the browser,
which changes daily and cannot be turned back. Robots and link previews are left out; the console is not counted.
The connection address is kept only until the 10-minute cron (`bin/notify.php`) has looked up its country with
the IPzentra key, and never longer than an hour. Lines are kept 400 days. A link shared with
`?utm_source=name` shows up under that name. Database change 014 adds the table.

## Sharing picture, search engines and page speed

- **Link previews.** Public pages (home, a project's page, privacy) carry Open Graph and Twitter tags and a
  canonical link. The picture is `/share.jpg`, 1200 by 630: the first four example photos behind
  `public/assets/share-frame.png` (`app/lib/Share.php`), or `public/assets/share.jpg` when fewer than four are set.
- **Search engines.** `/robots.txt` and `/sitemap.xml` on the contributor site list the public pages only. Pages
  behind sign-in say `noindex`. The console is closed to search engines; on the client portal only `/login` is open.
- **Speed.** Example photos are served in the width the page shows (`?w=160|320|480|640`, WebP, made once and
  kept in `storage/phones/sized`). Asset links carry the file's date (`asset()`), and `pw-deploy` writes
  `/etc/apache2/conf-available/photowall-speed.conf` so files under `/assets` are kept by browsers for a year and
  text is sent compressed. `bin/build.php` (run by `pw-deploy`) makes `public/assets/app.min.css`; pages use it
  when it is newer than `app.css`. The font files keep their names for good: rename a font file if it changes.
- **Checks used.** The W3C Nu HTML checker on every page type, axe on every page type at phone and desktop width,
  and Lighthouse on the public pages.

## Email sending log and client invitations

Every email the application tries to send goes through `Mailer::send()` and is written to `mail_log`: who to,
what kind, and whether it was sent, failed (with the mail server's reason) or only written to the file because
email is switched off. A sign-in code in the subject is blanked. The console shows it under Settings, Email,
and in full at `/settings/mail-log`; the hourly cron keeps 90 days. A client person is emailed an invitation
with the portal link when access is given, and "Send invitation again" on the client's page repeats it.
Database change 012 adds `mail_log` and `client_users.invited_at`.

Emails to a client's people (invitation, client sign-in code, answers to download requests) are sent from the
client sender address set under Settings, Email. It is `support@researchandmetric.com` until changed, and an
empty field means the same address as every other email. The mail account must be allowed to send as that
address. If the mail server refuses it, the email is sent from the usual address instead and the log says so.
Database change 013 adds the sender address to the log.

## Console settings page

Settings is one page in five parts (Email, Sign-in checks, Example photos, Invitation, Storage), switched by
the list on the left; the part after `#` in the address chooses one. A saved API key is shown only as its
first four and last three characters (`mask_key()`); the SMTP password is never shown. The activity list
shows each stored code in plain words (`activity_words()` in `app/lib/helpers.php`).

## Checks at sign-in and the invitation

Console, Settings, "Checks at sign-in". Both checks are off until a key is saved and the box is ticked.

- **IPzentra** (`GET https://api.ipzentra.com/v1/{ip}`, key in `X-Api-Key`): sign-in is refused when the
  connection is a VPN, proxy, Tor or data centre address. The country of the connection is saved with the
  contributor. A different country chosen by the contributor is accepted; they see a note and staff see both.
- **BounceBell** (`GET https://bouncebell.com/api/v1/verify?email=`, key as a bearer token): new contributors
  with an address that does not exist or is temporary are refused. Catch-all and unknown are let through.

If a service is off, has no key or does not answer, the check is skipped. Refusals are in Activity as
`signin.refused`. The visitor's address is `REMOTE_ADDR`: if the site is ever put behind a proxy or CDN, set
up Apache `mod_remoteip` first, or every visitor will look like a data centre. The code is in
`app/lib/Guard.php`. The invitation box (Reward Hours by default) is set under "Invitation shown to
contributors"; it is only a link, and no data is shared with Reward Hours.

The public project page is built from the project's settings: its description, the list of what to
photograph (one line each, shown with a matching icon), the limits and the terms. A respondent can
resend the code or correct the email on the code screen, and change their email after signing in
(confirmed by a code sent to the new address).

## Where the originals are kept

By default on the server's disk. To keep them in a DigitalOcean Spaces bucket instead, run
`pw-spaces setup` on the server. It asks for the region address, bucket, folder and keys, tests them
(upload, read back, delete, and that the file is not public), and only then switches the bucket on.

- Every original is uploaded as a private file under `<folder>/originals/<project>/`.
- A download sends the person to a private link that works for 5 minutes. ZIP downloads are built by
  the server from the bucket.
- Rejecting or deleting a photo removes it from the bucket.
- Small previews and thumbnails stay on the server so pages load fast.
- `pw-spaces move` moves photos that are already on the server into the bucket.

Every check has its threshold in the project settings, and the console lists every upload that was
turned away together with the information read from the file.

## Install on a server (Ubuntu, Apache or nginx)

1. Point three DNS A records at the server: the three addresses above.
2. As root, paste the contents of `deploy/setup-server.sh` into the terminal. It creates a deploy key
   and prints it. Add the key to this repository (Settings, Deploy keys, read only).
3. Run `pw-deploy`. It fetches the code and sets up everything: storage, scanner, database, settings
   file, web server site, https certificate (when DNS is ready and certbot is present), hourly clean-up.
4. Create your console login: `pw-admin add you@example.com "Your Name"`.
5. Sign in to the console and open **Settings** to enter your email (SMTP or Postmark) details, then use
   "Send test email". Until email works, sign-in codes are only written to
   `/var/lib/photowall/logs/mail.log` (`pw-admin code` shows the last one).

Updating later is one command: `pw-deploy`. It checks the new code for errors before switching to it.
`pw-deploy back` returns to the version that was running before.

## Commands on the server

| Command | Use |
| --- | --- |
| `pw-deploy` | Install the latest code from GitHub |
| `pw-deploy back` | Go back to the previous version |
| `pw-admin add EMAIL "Name"` | Create an administrator (prints a password) |
| `pw-admin reset EMAIL` | New password for a staff account |
| `pw-admin list` | Show staff accounts |
| `pw-check` | Check that everything the app needs is working |
| `pw-check /path/photo.HEIC` | Show exactly what the checks say about one photo |
| `pw-spaces setup` | Connect a Spaces bucket for the original photos |
| `pw-spaces status` / `test` / `move` / `off` | Where photos are, check the bucket, move photos into it, stop using it |

## Where things are

| Path | What |
| --- | --- |
| `/var/www/photowall` | The app (this repository). Only `public/` is served. |
| `/etc/photowall/config.php` | Settings and secrets. Never in git. |
| `/var/lib/photowall` | Photos (`originals`, `previews`, `thumbs`), logs, models, sessions |
| `/opt/photowall-venv` | Python environment for the scanner |

## Honest limits

- The device check reads the information the phone writes into the file. A person with technical
  skill can forge that information. The strict mode makes this much harder, not impossible.
- Edits made inside Apple's own Photos app are caught when they change the size (crop), but a colour
  or filter edit made there can look like an original.
- The safety scan looks for nudity and sexual content. It does not recognise violence, minors, or
  other unsuitable content, and no model is perfect. Staff approval is the real gate.
- Test with a real iPhone 16 before publishing a project link: upload from Safari, then look at
  "Turned away" in the console or run `pw-check` on a file.

## Built with

PHP 8.1+ (no framework), MySQL or MariaDB, exiftool for metadata, and a Python scanner using Pillow,
pillow-heif, OpenCV, the Yahoo OpenNSFW model (BSD 2-Clause, downloaded at install) and NudeNet.
