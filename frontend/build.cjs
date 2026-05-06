const webpack = require('webpack');
const config = require('./webpack.config.cjs');

webpack(config, (err, stats) => {
    if (err) {
        console.error(err.stack || err.message);
        process.exit(1);
    }

    const info = stats.toJson();
    if (stats.hasErrors()) {
        console.error(info.errors);
        process.exit(1);
    }

    if (stats.hasWarnings()) {
        console.warn(info.warnings);
    }

    console.log('Node Manager (PM2) Plesk UI Library bundle compiled.');
});
