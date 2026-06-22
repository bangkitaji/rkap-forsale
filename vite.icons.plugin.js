import fs from 'fs/promises';
import path from 'path';
import { getIconsCSS } from '@iconify/utils';
import { glob } from 'glob';

export default function iconifyPlugin() {
  return {
    name: 'vite-iconify-plugin',
    apply: 'build', // Run only during build

    async buildStart() {
      console.log('🔨 Scanning templates and scripts for used Boxicons...');

      try {
        // 1. Scan blade templates and javascript files for icon classes (bx-*, bxs-*, bxl-*)
        const files = glob.sync('resources/{views,js}/**/*.{php,js}');
        const iconRegex = /\bbx[sl]?-[a-z0-9-]+/g;
        const usedIconClasses = new Set();

        for (const file of files) {
          const content = await fs.readFile(file, 'utf8');
          let match;
          while ((match = iconRegex.exec(content)) !== null) {
            usedIconClasses.add(match[0]);
          }
        }

        // Map class names (e.g. bx-user, bxs-circle) to their base icon names and prefixes
        const prefixMapping = { bx: new Set(), bxs: new Set(), bxl: new Set() };
        usedIconClasses.forEach(className => {
          const parts = className.split('-');
          const prefix = parts[0];
          const name = parts.slice(1).join('-');
          if (prefixMapping[prefix]) {
            prefixMapping[prefix].add(name);
          }
        });

        console.log(`🔍 Found ${usedIconClasses.size} unique icons in views/scripts.`);

        const iconSetConfigs = [
          { path: 'node_modules/@iconify/json/json/bx.json', prefix: 'bx' },
          { path: 'node_modules/@iconify/json/json/bxl.json', prefix: 'bxl' },
          { path: 'node_modules/@iconify/json/json/bxs.json', prefix: 'bxs' }
        ];

        let allIconsCSS = '';

        for (const set of iconSetConfigs) {
          const rawData = await fs.readFile(path.resolve(process.cwd(), set.path), 'utf-8');
          const iconSet = JSON.parse(rawData);

          // Filter keys to only those actually used in the templates
          const usedKeys = Array.from(prefixMapping[set.prefix]).filter(
            key => (iconSet.icons && iconSet.icons[key]) || (iconSet.aliases && iconSet.aliases[key])
          );

          if (usedKeys.length > 0) {
            const css = getIconsCSS(iconSet, usedKeys, {
              iconSelector: '.{prefix}-{name}',
              commonSelector: '.bx',
              format: 'expanded'
            });
            allIconsCSS += css + '\n';
          }
        }

        const outputPath = path.resolve(process.cwd(), 'resources/assets/vendor/fonts/iconify/iconify.css');
        const dir = path.dirname(outputPath);
        await fs.mkdir(dir, { recursive: true });
        await fs.writeFile(outputPath, allIconsCSS, 'utf8');

        console.log(`✅ Compressed Iconify CSS generated at: ${outputPath}`);
      } catch (error) {
        console.error('❌ Error generating Iconify CSS:', error);
      }
    }
  };
}
