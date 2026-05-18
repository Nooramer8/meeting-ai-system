export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui']
      },
      boxShadow: {
        soft: '0 18px 45px rgba(25, 49, 80, 0.10)'
      }
    }
  },
  plugins: []
}
