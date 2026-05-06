# Third Party Notices

This extension builds its browser UI with Plesk's React UI Library tooling.

- React: MIT License, https://github.com/facebook/react
- Plesk Extension SDK / UI Library: Apache-2.0 style SDK package from Plesk,
  https://www.npmjs.com/package/@plesk/plesk-ext-sdk

Runtime bundles are generated into `htdocs/dist/`. Do not edit generated bundle
files directly; update `frontend/` and rebuild with `npm run build`.
