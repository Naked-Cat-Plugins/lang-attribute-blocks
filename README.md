# Language Attribute for Container Blocks and Pages/Posts

A WordPress plugin that adds `lang` and `dir` attributes to Group, Columns, Cover, and other specific WordPress Blocks, or to the whole page/post for improved accessibility.

## Description

This plugin provides a way to ensure that language changes are correctly indicated to assistive technologies, helping a website comply with WCAG guidelines. It works at two levels: at the **block level** (Group, Columns, Cover, and other container blocks) for sections within a page written in a different language (WCAG 3.1.2), and at the **page/post level** to override the HTML `lang` and `dir` attributes for an entire page written in a different language (WCAG 3.1.1).

## Build Instructions

### Prerequisites

- Node.js (version 16 or higher)
- npm or yarn package manager
- WordPress development environment

### Development Setup

1. Clone or download the plugin to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/Naked-Cat-Plugins/lang-attribute-blocks.git lang-attribute-blocks
   cd lang-attribute-blocks
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Start development mode with hot reloading:
   ```bash
   npm run start
   ```

### Build for Production

1. Create a production build:
   ```bash
   npm run build
   ```

2. The built files will be generated in the `build/` directory:
   - `build/index.js` - Compiled JavaScript
   - `build/index.css` - Compiled CSS

### Build Scripts

- `npm run start` - Start development mode with file watching
- `npm run build` - Create production build
- `npm run make-pot` - Update .pot file

## How can I report security bugs?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/ce04f590-44d9-45f3-9411-9028e87d4725)