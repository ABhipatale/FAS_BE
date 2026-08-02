import { writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Write public/index.html on build, referencing the freshly built assets.
 *
 * Laravel still boots through public/index.php and resolves assets via @vite()
 * and the manifest — this is a plain static page sitting next to the front
 * controller. It does not intercept API traffic: public/.htaccess only rewrites
 * to index.php when the request matches no real file or directory, and XAMPP's
 * DirectoryIndex lists index.php ahead of index.html, so "/" still reaches
 * Laravel. The page is served at /index.html.
 */
function buildIndexHtml({ title }) {
    let base = '/';
    let isSsr = false;
    let outFile = '';

    const escape = (value) =>
        String(value).replace(
            /[&<>"']/g,
            (char) =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                })[char],
        );

    return {
        name: 'build-index-html',
        apply: 'build',
        enforce: 'post',

        configResolved(config) {
            base = config.base;
            isSsr = Boolean(config.build.ssr);

            // outDir is public/build — step up so the page lands in public/,
            // which also keeps this correct if buildDirectory is renamed.
            const outDir = resolve(config.root, config.build.outDir);
            outFile = resolve(dirname(outDir), 'index.html');
        },

        writeBundle(_options, bundle) {
            if (isSsr) {
                return;
            }

            const styles = new Set();
            const scripts = [];

            for (const output of Object.values(bundle)) {
                if (output.type === 'asset') {
                    if (output.fileName.endsWith('.css')) {
                        styles.add(output.fileName);
                    }
                    continue;
                }

                if (output.isEntry) {
                    scripts.push(output.fileName);

                    for (const css of output.viteMetadata?.importedCss ?? []) {
                        styles.add(css);
                    }
                }
            }

            const prefix = base.endsWith('/') ? base : `${base}/`;
            const url = (file) => escape(prefix + file);

            const head = [...styles]
                .map((file) => `    <link rel="stylesheet" href="${url(file)}">`)
                .join('\n');

            const body = scripts
                .map((file) => `    <script type="module" src="${url(file)}"></script>`)
                .join('\n');

            writeFileSync(
                outFile,
                `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${escape(title)}</title>
    <link rel="icon" href="/favicon.ico">
${head}
</head>
<body>
    <div id="app">
        <!-- Placeholder: replace once something mounts onto #app. Styles are
             inline so this renders even if the CSS bundle is not loaded. -->
        <main style="font-family: system-ui, sans-serif; padding: 4rem 1.5rem; text-align: center; color: #1b1b18;">
            <h1 style="margin: 0 0 .5rem; font-size: 1.25rem; font-weight: 600;">${escape(title)}</h1>
            <p style="margin: 0; color: #706f6c;">Backend is running.</p>
        </main>
    </div>
${body}
</body>
</html>
`,
            );
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
            buildIndexHtml({ title: env.APP_NAME || 'Laravel' }),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
