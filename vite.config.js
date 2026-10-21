import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// WPFlatInvoice is a separate bundle that runs inside WPFlatCore's page: Vue is not bundled again, it is WPFlatCore's window.Vue.
export default defineConfig( {
	root: 'ui',
	plugins: [ vue() ],
	define: { 'process.env.NODE_ENV': '"production"' },
	build: {
		outDir: '../build',
		emptyOutDir: true,
		cssCodeSplit: false,
		lib: { entry: 'main.js', name: 'WPFlatInvoice', formats: [ 'iife' ], fileName: () => 'wpflatinvoice.js', cssFileName: 'wpflatinvoice' },
		rollupOptions: { external: [ 'vue' ], output: { globals: { vue: 'Vue' } } },
	},
} );
