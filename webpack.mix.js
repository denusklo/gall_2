// webpack.mix.js
const path = require('path');
const mix = require('laravel-mix');

mix.js('resources/js/galleryApp.js', 'public/js')
   .vue()
   .sass('resources/sass/app.scss', 'public/css');

if (!mix.inProduction()) {
    mix.options({
        // publicPath: '/', // Keep commented out for now

        // hmrOptions is what Mix uses to configure the HMR client
        // and to write the contents of the 'public/hot' file.
        hmrOptions: {
            host: 'gallery_2.localhost.dev', // The public host Nginx serves
            port: 443,                       // The public port Nginx serves (HTTPS)
            // Mix should automatically prepend https:// if port is 443,
            // or you can be explicit if needed:
            public: 'https://gallery_2.localhost.dev' // This might also be an option for hmrOptions
        },
    });

    mix.webpackConfig({
        devServer: {
            host: '0.0.0.0', // Dev server listens on this inside the container
            port: 8080,      // Dev server listens on this port inside the container
            https: false,    // Dev server itself is HTTP

            // public: `https://gallery_2.localhost.dev`, // REMOVE THIS for Webpack Dev Server v4

            // publicPath: '/', // Keep commented out for now

            static: {
              directory: path.resolve(__dirname, 'public'),
            },

            headers: {
                'Access-Control-Allow-Origin': '*',
            },
            proxy: undefined, // We're using Nginx for proxying
        }
    });
}

if (mix.inProduction()) {
    mix.version();
}