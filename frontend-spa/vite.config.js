import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react({
    fastRefresh: false // This turns off strict fast refresh alerts for context wrappers
  })],
})