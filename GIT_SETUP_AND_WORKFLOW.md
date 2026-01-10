# Git Setup and Workflow Documentation

## Initial Setup Completed (January 10, 2026)

### Local Development Environment (Windows - Laragon)
**Location:** `c:\laragon\www\amyofootball`

#### What Was Done:
1. ✅ Initialized git repository
2. ✅ Added GitHub remote: `git@github.com:dstang21/amyofootball.git`
3. ✅ Created `.gitignore` to exclude sensitive files
4. ✅ Set up SSH key for GitHub authentication
5. ✅ Made initial commit with 83 files
6. ✅ Pushed to GitHub on `main` branch

#### SSH Key Details:
- **Email:** millersportscards@gmail.com
- **Key Location:** `C:\Users\Derek\.ssh\id_ed25519`
- **Added to GitHub:** https://github.com/settings/keys

### Live Production Server (Hostinger)
**Location:** `/home/u596677651/domains/amyofootball.com/public_html`

#### What Was Done:
1. ✅ Initialized git repository
2. ✅ Added GitHub remote (HTTPS): `https://github.com/dstang21/amyofootball.git`
3. ✅ Created snapshot of existing server files
4. ✅ Connected to GitHub `main` branch
5. ✅ Recovered `config.php` from git history
6. ✅ Verified site is working

#### Important Files:
- **config.php** - Contains database credentials (excluded from git via `.gitignore`)
  - Database: `u596677651_football`
  - User: `u596677651_fball`
  - Host: `localhost`

---

## Daily Workflow

### Making Changes Locally (Windows)

1. **Make your code changes** in VS Code or your editor

2. **Check what changed:**
   ```bash
   git status
   ```

3. **Stage your changes:**
   ```bash
   # Stage all changes
   git add .
   
   # Or stage specific files
   git add path/to/file.php
   ```

4. **Commit with a descriptive message:**
   ```bash
   git commit -m "Add feature: description of what you did"
   ```
   
   Good commit message examples:
   - `"Fix SleeperSync roster update bug"`
   - `"Add fantasy league standings page"`
   - `"Update scoring calculations for 2026 season"`

5. **Push to GitHub:**
   ```bash
   git push
   ```

### Deploying to Live Server

**SSH into your Hostinger server:**
```bash
ssh u596677651@us-bos-web1713.main-hosting.eu
```

**Navigate to site directory:**
```bash
cd /home/u596677651/domains/amyofootball.com/public_html
```

**Pull latest changes:**
```bash
git pull origin main
```

**Verify the site still works:**
- Visit: https://amyofootball.com
- Check that no errors appear

---

## Important Notes

### Files Excluded from Git (.gitignore)

These files are intentionally NOT tracked in git:

- **config.php** - Contains database passwords
- ***.sql** - Database dumps (sensitive data)
- **.vscode/**, **.idea/** - IDE settings
- **vendor/** - Composer dependencies (if added)
- **uploads/**, **user-content/** - User-uploaded files

**⚠️ Critical:** `config.php` must be manually maintained on each environment:
- Local config points to: `localhost / u596677651_football`
- Server config points to: `localhost / u596677651_football` (same in this case)

### If config.php Gets Deleted on Server

**Recover from git history:**
```bash
cd /home/u596677651/domains/amyofootball.com/public_html
git show HEAD:config.php > config.php
```

Or recreate it manually with the correct credentials.

---

## Common Scenarios

### Scenario 1: Made changes on server by accident

If you edited files directly on the live server:

```bash
# On the server
cd /home/u596677651/domains/amyofootball.com/public_html
git status  # See what changed
git diff filename.php  # Review specific changes

# Option A: Keep the changes and push to GitHub
git add .
git commit -m "Emergency fix on live server"
git push origin main

# Then pull on local machine
cd c:\laragon\www\amyofootball
git pull origin main

# Option B: Discard server changes and use GitHub version
git reset --hard origin/main
```

### Scenario 2: Local and server have diverged

```bash
# On local machine
git pull origin main  # Try to merge
# If conflicts occur, resolve them in VS Code
git add .
git commit -m "Merge conflicts resolved"
git push origin main
```

### Scenario 3: Need to rollback to previous version

```bash
# View commit history
git log --oneline

# Rollback to specific commit
git checkout <commit-hash>

# Or revert last commit
git revert HEAD
git push origin main
```

### Scenario 4: Check differences between local and GitHub

```bash
# See what's different before pulling
git fetch origin
git diff main origin/main

# See list of changed files
git diff --name-status main origin/main
```

---

## Best Practices

### ✅ DO:
- Commit frequently with clear messages
- Pull before you push to avoid conflicts
- Test changes locally before deploying to live server
- Keep `config.php` backed up separately
- Use feature branches for major changes

### ❌ DON'T:
- Commit `config.php` or database dumps
- Edit files directly on live server (use git workflow)
- Force push (`git push -f`) unless absolutely necessary
- Commit without testing first
- Leave sensitive credentials in code

---

## Useful Git Commands

```bash
# Check current status
git status

# View commit history
git log --oneline -10

# See what changed in last commit
git show HEAD

# Undo last commit (keep changes)
git reset --soft HEAD~1

# Undo last commit (discard changes)
git reset --hard HEAD~1

# View all branches
git branch -a

# Create new feature branch
git checkout -b feature-name

# Switch back to main
git checkout main

# View remote repositories
git remote -v

# Check differences
git diff
git diff filename.php
git diff origin/main

# Discard local changes to file
git checkout -- filename.php
```

---

## Emergency Contacts

- **GitHub Repository:** https://github.com/dstang21/amyofootball
- **Live Site:** https://amyofootball.com
- **GitHub Email:** millersportscards@gmail.com
- **Hostinger Server:** us-bos-web1713.main-hosting.eu
- **SSH User:** u596677651

---

## Next Steps to Consider

1. **Set up automated deployments** - Use GitHub Actions to auto-deploy on push
2. **Add staging environment** - Test changes before going live
3. **Database migrations** - Version control your database schema changes
4. **Backup strategy** - Regular automated backups of database and files
5. **Branch protection** - Require pull requests for main branch changes

---

*Last Updated: January 10, 2026*
*Setup by: GitHub Copilot + Derek*
