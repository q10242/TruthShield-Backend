# TruthShield Beta Test Guide

## Goal

Use this guide to run a local or closed beta session with testers. The goal is to verify that a user can install the extension, understand the banner, read a news page, vote with evidence, and inspect their profile achievements.

## Tester Accounts

- Test user: `checker@example.com` through local login.
- Admin user: `admin@truthshield.local` / `admin123456` in the Laravel admin panel.

## Before The Session

- Start Docker: `docker compose up --build`.
- Confirm API health: `http://127.0.0.1:18080/api/system/health`.
- Open web app: `http://127.0.0.1:15173`.
- Load unpacked extension from `truth-shield-web/public/extension`.
- In extension options, set:
  - Web origin: `http://127.0.0.1:15173`
  - API origin: `http://127.0.0.1:18080`

## Core User Flow

1. Open `http://127.0.0.1:15173/local-news-demo`.
2. Confirm the top TruthShield banner appears once.
3. Click the banner and confirm the vote/evidence panel opens.
4. Log in with local test credentials.
5. Wait for the reading threshold to complete.
6. Submit a vote.
7. If using a negative label, submit an external evidence URL and one short explanation.
8. Open `/profile` and confirm the vote count and achievements update.
9. Open `/evidence-library` and confirm the evidence appears.
10. Open `/transparency` and confirm public counters changed.

## Extension Flow On Real News Sites

For each target domain:

- Hover a news link and confirm tooltip appears after about 300ms.
- Move the mouse away and confirm tooltip disappears.
- Open an article page and confirm the top banner appears.
- Close the banner and confirm it does not reappear until refresh.
- Click the banner and confirm panel opens.
- Use the extension popup to open status, vote panel, and report flow.

## Feedback To Collect

- Did the user understand what the top banner means?
- Did the user understand why reading time is required?
- Did the user know which evidence URL is acceptable?
- Did the user notice achievements on profile?
- Did any page feel too dense or confusing?
