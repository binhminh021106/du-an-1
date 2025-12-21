import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue({
      template: {
        compilerOptions: {
          // Cấu hình này bảo Vue rằng 'lord-icon' là thẻ tùy chỉnh (Web Component)
          // Vue sẽ bỏ qua nó trong quá trình biên dịch component, giúp hết lỗi warning
          isCustomElement: (tag) => tag === 'lord-icon'
        }
      }
    })
  ],
})