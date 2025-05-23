# Deploying Invoice Ninja on Render.com Free Tier

This guide explains how to deploy Invoice Ninja on Render.com's free tier using a custom Dockerfile.

## Prerequisites

1. A [Render.com](https://render.com) account
2. An external database service (options described below)
3. The `Dockerfile.render` from this repository

## External Database Options

Since Render's free tier doesn't include persistent storage, you'll need an external database. Options include:

- **Supabase PostgreSQL** (free tier available)
- **Neon PostgreSQL** (free tier available)
- **PlanetScale MySQL** (free tier available)
- **Railway MySQL/PostgreSQL** (limited free tier)

## Step 1: Set up your Database

1. Create a PostgreSQL or MySQL database on one of the services mentioned above
2. Note your database connection string which will look something like:
   ```
   mysql://username:password@host:port/database
   ```
   or
   ```
   postgresql://username:password@host:port/database
   ```

## Step 2: Deploy to Render

1. Log in to your Render.com account
2. Click "New" and select "Web Service"
3. Choose "Build and deploy from a Git repository"
4. Connect your GitHub/GitLab account and select this repository
5. Configure the service:
   - **Name**: Choose a name (e.g., "invoice-ninja")
   - **Runtime**: Docker
   - **Dockerfile Path**: `Dockerfile.render`
   - **Branch**: `main` (or your preferred branch)
   
6. Add the following environment variables:
   ```
   APP_URL=https://your-app-name.onrender.com
   APP_KEY=base64:GenerateARandomKeyHere (you can use online base64 generators)
   APP_DEBUG=false
   REQUIRE_HTTPS=true
   DATABASE_URL=your_database_connection_string_from_step_1
   CACHE_DRIVER=file
   QUEUE_CONNECTION=sync
   SESSION_DRIVER=file
   PDF_GENERATOR=snappdf
   IN_USER_EMAIL=your_admin_email@example.com
   IN_PASSWORD=your_secure_password
   MAIL_MAILER=log
   ```

7. Select the free plan
8. Click "Create Web Service"

## Step 3: Finalize Setup

1. Wait for the deployment to complete (this may take several minutes)
2. Once deployed, your Invoice Ninja instance will be available at `https://your-app-name.onrender.com`
3. Log in with the admin email and password you specified in the environment variables

## Important Limitations

When using Render's free tier, be aware of these limitations:

1. **Sleep Mode**: Free services sleep after 15 minutes of inactivity. The first request after sleeping will take time to respond.
2. **Non-Persistent Storage**: Any files uploaded will be lost when the service redeploys. Consider using:
   - S3-compatible storage for file uploads
   - External database for all data
3. **Limited Resources**: The free tier has CPU and RAM constraints, so performance may be limited for larger datasets.

## Production Recommendations

For a production municipal payment portal, consider:
1. Upgrading to a paid Render tier
2. Using persistent disk storage
3. Setting up a proper email provider instead of log driver
4. Configuring backups for your database and files 