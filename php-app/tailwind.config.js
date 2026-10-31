/** Builds php-app/public/assets/app.css from the classes used in the PHP pages and JS. */
const c = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;
module.exports = {
  content: ["./php-app/public/**/*.php", "./php-app/public/assets/*.js"],
  theme: {
    extend: {
      colors: {
        cream: c("cream"), sand: c("sand"), terracotta: c("terracotta"), rust: c("rust"),
        olive: c("olive"), charcoal: c("charcoal"), warmgray: c("warmgray"),
      },
      fontFamily: {
        display: ['"Fraunces"', "Georgia", "serif"],
        sans: ['"DM Sans"', "system-ui", "sans-serif"],
      },
    },
  },
  plugins: [],
};
