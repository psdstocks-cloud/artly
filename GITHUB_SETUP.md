# GitHub Repository Setup Instructions

This guide will walk you through creating a GitHub repository and pushing your Artly project to it.

## Prerequisites

- GitHub account (sign up at https://github.com if needed)
- Git installed on your system (already verified - you have git initialized)
- GitHub CLI (`gh`) OR access to GitHub web interface

## Option 1: Using GitHub Web Interface (Recommended)

### Step 1: Create Repository on GitHub

1. Go to https://github.com and log in
2. Click the **"+"** icon in the top right corner
3. Select **"New repository"**
4. Fill in the repository details:
   - **Repository name:** `artly` (or your preferred name)
   - **Description:** "Artly WordPress theme with Nehtw Gateway plugin - Points-based wallet system with WooCommerce integration"
   - **Visibility:**
     - **Private** - Recommended for production projects
     - **Public** - If you want to open source it
   - **DO NOT** initialize with README, .gitignore, or license (we already have these)
5. Click **"Create repository"**

### Step 2: Connect Local Repository to GitHub

After creating the repository, GitHub will show you commands. Use these commands in your terminal:

```bash
cd "/Users/ahmedabdelghany/Local Sites/artly"

# Add the remote repository (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/artly.git

# Or if you prefer SSH (requires SSH key setup):
# git remote add origin git@github.com:YOUR_USERNAME/artly.git

# Rename branch to main (if needed)
git branch -M main

# Push to GitHub
git push -u origin main
```

### Step 3: Authenticate

If you're using HTTPS, Git will prompt you for credentials:
- **Username:** Your GitHub username
- **Password:** Use a Personal Access Token (not your GitHub password)
  - Generate token: https://github.com/settings/tokens
  - Select "repo" scope
  - Copy the token and use it as the password

## Option 2: Using GitHub CLI

### Step 1: Install GitHub CLI (if not installed)

```bash
# macOS
brew install gh

# Or download from: https://cli.github.com/
```

### Step 2: Authenticate

```bash
gh auth login
```

Follow the prompts to authenticate with GitHub.

### Step 3: Create Repository and Push

```bash
cd "/Users/ahmedabdelghany/Local Sites/artly"

# Create repository (private by default, add --public for public repo)
gh repo create artly --private --source=. --remote=origin --push

# Or if you want it public:
# gh repo create artly --public --source=. --remote=origin --push
```

## Verify Setup

After pushing, verify your repository:

1. Go to your GitHub repository: `https://github.com/YOUR_USERNAME/artly`
2. You should see:
   - README.md
   - .gitignore
   - All theme files in `app/public/wp-content/themes/artly/`
   - All plugin files in `app/public/wp-content/plugins/nehtw-gateway/`

## Next Steps

### Set Repository Description

1. Go to your repository on GitHub
2. Click the **⚙️ Settings** gear icon (or go to Settings tab)
3. Scroll to "About" section
4. Add description: "Artly WordPress theme with Nehtw Gateway plugin"
5. Add topics: `wordpress`, `theme`, `woocommerce`, `php`, `javascript`, `gsap`

### Configure Branch Protection (Optional)

For production projects, you may want to protect the main branch:

1. Go to **Settings → Branches**
2. Add branch protection rule for `main`
3. Enable:
   - Require pull request reviews
   - Require status checks
   - Require branches to be up to date

### Add Collaborators (Optional)

1. Go to **Settings → Collaborators**
2. Click **"Add people"**
3. Enter GitHub usernames or email addresses
4. Choose permission level (Read, Write, or Admin)

### Set Up GitHub Actions (Optional)

You can set up CI/CD workflows later for:
- Automated testing
- Code linting
- Deployment automation

## Future Updates

To push future changes:

```bash
cd "/Users/ahmedabdelghany/Local Sites/artly"

# Stage changes
git add .

# Commit changes
git commit -m "Your commit message describing the changes"

# Push to GitHub
git push origin main
```

## Troubleshooting

### "Repository not found" error

- Verify the repository URL is correct
- Ensure you're authenticated (check with `gh auth status`)
- Verify you have access to the repository

### "Permission denied" error

- Use Personal Access Token instead of password
- Check SSH key setup if using SSH
- Verify repository permissions

### "Large files" error

- If you have large files (>100MB), use Git LFS:
  ```bash
  git lfs install
  git lfs track "*.otf"
  git lfs track "*.ttf"
  git add .gitattributes
  git commit -m "Add LFS tracking for font files"
  git push origin main
  ```

## Security Notes

⚠️ **Important:** Before pushing to GitHub, ensure:

1. ✅ No sensitive data in code (API keys, passwords)
2. ✅ `wp-config.php` is in `.gitignore` (already done)
3. ✅ No database credentials committed
4. ✅ Personal Access Token has minimal required scopes

## Repository URL Template

After setup, your repository will be at:
```
https://github.com/YOUR_USERNAME/artly
```

Replace `YOUR_USERNAME` with your actual GitHub username.

---

**Need Help?**
- GitHub Docs: https://docs.github.com
- Git Documentation: https://git-scm.com/doc

