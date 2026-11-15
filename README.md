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

Everyone who joins gets a contributor ID (RM-C0001), and every kept photo an image ID (RM-I00001). On the upload
page the contributor chooses a category before choosing photos: the project's "What to photograph" lines plus
Other. The category can be changed per photo while it waits for review, and by staff on the photo's page.

When staff accept photos, the contributor gets an email listing them (image ID, file name, category, date),
their totals, the reward earned so far and their contributor ID. `bin/notify.php` runs every 10 minutes from
cron and sends one email per contributor, once staff have stopped approving that person's photos for five
minutes, so a review session gives one email and not one per photo. The contributor's upload page shows the same
details for every photo. The code is in `app/lib/Notify.php`.

The console has a Tracker for each project (linked from the project page): one line for every photo that reached
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
