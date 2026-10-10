# Spirited Finds — PHP/MySQL Render deployment

The `render-php` branch contains the complete PHP version copied from `yuukifromdblock/Spirited-Finds` plus Docker and deployment configuration. The GitHub Pages `main` branch is unchanged.

## Deployment

1. In Render, create a **Web Service** from `https://github.com/yuwi010101/Spirited-Finds-Render`.
2. Select branch **render-php**, runtime **Docker**, and the Dockerfile `./Dockerfile`.
3. Set the following environment variables in Render (DO NOT put credentials in GitHub):
   - `DB_REQUIRE_REMOTE=1`
   - `DB_HOST` = remote MySQL host (not localhost)
   - `DB_PORT` = remote MySQL port (often 3306)
   - `DB_NAME` = remote database name
   - `DB_USER` = remote database username
   - `DB_PASSWORD` = remote database password
4. Create the database with an external MySQL provider that accepts Render connections, then import `spirited_finds_db (1).sql`.
5. Deploy and test sign in, browsing, orders and admin features.

Alternatively, use Render's **New > Blueprint**, select branch `render-php`, and path `render.yaml`. Do not create both a Blueprint web service and a separate manual web service for the same app.

## Notes

- The public site requires MySQL and will display a database configuration error until the remote database is configured.
- SQL dumps are excluded from the container image and Apache directory browsing is disabled.
- Locally, `config/db.php` still works with XAMPP (`localhost`, `root`, empty password) when the Render-only environment variables are not set.
- Product-image uploads in `admin/products.php` currently write to local `images/` and **will be lost** on Render's ephemeral free web service filesystem. For reliable uploads, update the app to use object storage.
- A MySQL provider may impose TLS settings or outbound network rules that require more configuration.
- Use the Render web-service URL for the PHP system; GitHub Pages only hosts the HTML version on `main`.
