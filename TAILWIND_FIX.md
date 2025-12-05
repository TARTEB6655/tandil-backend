# Fix Tailwind CSS Classes Not Working

## Quick Fixes:

### 1. **Development Mode** (Recommended for development)
Run Vite dev server in a separate terminal:
```bash
npm run dev
```
Keep this running while developing. It will auto-rebuild when you make changes.

### 2. **Production Mode** (After making changes)
Rebuild assets:
```bash
npm run build
```

### 3. **Clear All Caches**
```bash
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize:clear
```

### 4. **Browser Cache**
- Hard refresh: `Ctrl + Shift + R` (Windows/Linux) or `Cmd + Shift + R` (Mac)
- Or clear browser cache manually

### 5. **Check Tailwind Config**
Make sure `tailwind.config.js` includes all your view files:
```js
content: [
    './resources/views/**/*.blade.php',
    // ... other paths
]
```

## Common Issues:

1. **New classes not working**: Run `npm run build` or `npm run dev`
2. **Styles disappear after refresh**: Clear browser cache
3. **Some classes work, others don't**: Rebuild assets with `npm run build`
4. **Production vs Development**: Use `npm run dev` for dev, `npm run build` for production


