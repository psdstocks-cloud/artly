# Nehtw Gateway - Build Instructions

## Prerequisites

- Node.js (v14 or higher)
- npm or yarn

## Installation

1. Navigate to the plugin directory:
```bash
cd app/public/wp-content/plugins/nehtw-gateway
```

2. Install dependencies:
```bash
npm install
```

## Building

### Production Build
```bash
npm run build
```

This will compile the React component and output `subscription-dashboard.min.js` to `assets/js/`

### Development Build (with watch)
```bash
npm run dev
```

This will watch for changes and rebuild automatically.

## Output

- `assets/js/subscription-dashboard.min.js` - Compiled React component

## Notes

- React and ReactDOM are loaded as externals (from WordPress core or enqueued separately)
- The build process uses Babel to transpile JSX and modern JavaScript
- CSS is handled separately (already in `assets/css/nehtw-subscriptions.css`)

