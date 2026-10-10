# Storefront security and functionality improvements

This branch is the PHP/MySQL storefront (not GitHub Pages).

## Database setup and safe demo data

1. Set up a remote MySQL database that accepts connections from Render.
2. Import `spirited_finds_db (1).sql` (the sanitized seed includes catalog/categories but no sample customers, orders, or plaintext admin passwords).
3. Run `database/2026_10_security_reviews.sql` **once** to enable verified-purchase reviews and persisted checkout recipient/payment information. If you already have a database, back it up first and run only the migration.
4. Create your own admin with `ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a-long-unique-secret' php tools/create_admin.php` from a trusted CLI with the database environment configured. Do not leave the password in the repository.
5. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` in Render. `DB_REQUIRE_REMOTE=1` on Render prevents accidentally falling back to localhost.

## Checkout behavior
- Currently **Cash on Delivery only**. There is no live GCash/card processor. Do not collect or charge card details.
- Product pricing/stock are reloaded in a transaction at submission time, with row locks and a guarded stock decrement. Server-calculated totals include a flat PHP 100 delivery fee.
- Orders save recipient, phone, address and selected payment method; cart is removed after successful commit.
- Invalid/expired forms produce an error; client order history is scoped by user ID.

## Reviews
- A customer can leave or edit one 1–5 star review per product they have purchased in a **Completed** order.
- The displayed average and count are computed from review records, not editable/stored product.rating demo values.
- Comments are escaped on display and limited to 1,500 characters.

## Remaining real-world requirements
- Persistent media storage for admin-uploaded product images (Render free filesystem is ephemeral).
- Live payment gateway integration if you intend to accept anything other than COD.
- Real MySQL database, live testing of full checkout and admin flows, privacy notice and business checkout policies.
- Rotate any credentials present in the old public SQL history; deleting an INSERT from the latest branch does not erase Git history.
