const path = require('path');
const createBabelConfig = require('@plesk/plesk-ext-sdk/lib/babel/createConfig');
const extensionConfig = require('../extension.config');

const babelConfig = createBabelConfig();
babelConfig.presets = [
    [
        require.resolve('@babel/preset-env'),
        {
            modules: 'commonjs',
        },
    ],
    babelConfig.presets[1],
];
babelConfig.plugins = babelConfig.plugins.map(plugin => {
    const pluginName = Array.isArray(plugin) ? plugin[0] : plugin;
    if (String(pluginName).includes('@babel/plugin-transform-react-jsx')) {
        return [
            pluginName,
            {
                pragma: 'createElement',
            },
        ];
    }

    return plugin;
});

const config = {
    mode: 'production',
    context: __dirname,
    entry: './index.js',
    output: {
        filename: 'node-manager-pm2-ui.js',
        path: path.resolve(__dirname, '../htdocs/dist'),
        libraryTarget: 'amd',
    },
    devtool: 'source-map',
    module: {
        rules: [
            {
                test: /\.js$/i,
                include: [__dirname],
                loader: require.resolve('babel-loader'),
                options: {
                    cacheDirectory: true,
                    ...babelConfig,
                },
            },
        ],
    },
    externals: {
        '@plesk/plesk-ext-sdk': { amd: 'plesk-ui-library' },
        '@plesk/ui-library': { amd: 'plesk-ui-library' },
        react: { amd: 'plesk-ui-library' },
        'react-dom': { amd: 'plesk-ui-library' },
    },
};

module.exports = typeof extensionConfig.webpack === 'function'
    ? extensionConfig.webpack(config, { isDev: false })
    : config;
