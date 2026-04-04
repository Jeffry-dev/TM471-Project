// home.js

const slides = [
  {
    src: 'https://images.pexels.com/photos/6089614/pexels-photo-6089614.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Hummus with warm pita',
  },
  {
    src: 'https://images.pexels.com/photos/31233886/pexels-photo-31233886.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Fresh fattoush salad',
  },
  {
    src: 'https://images.pexels.com/photos/32986488/pexels-photo-32986488.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Mixed grill platter',
  },
  {
    src: 'https://images.pexels.com/photos/32023378/pexels-photo-32023378.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Shish tawook skewers',
  },
  {
    src: 'https://images.pexels.com/photos/5191816/pexels-photo-5191816.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Tabbouleh bowl',
  },
  {
    src: 'https://images.pexels.com/photos/15794015/pexels-photo-15794015.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Baklava dessert',
  },
  {
    src: 'https://images.pexels.com/photos/19559294/pexels-photo-19559294.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Knafeh slice',
  },
  {
    src: 'https://images.pexels.com/photos/32902704/pexels-photo-32902704.jpeg?auto=compress&cs=tinysrgb&w=1400',
    alt: 'Traditional Arabic coffee',
  },
];

function mountCarousel() {
  const host = document.getElementById('hero-carousel');
  if (!host) return;

  host.innerHTML = '';

  const slidesEls = slides.map((s) => {
    const slide = document.createElement('div');
    slide.className = 'carousel-slide';

    const img = document.createElement('img');
    img.src = s.src;
    img.alt = s.alt;
    img.className = 'object-cover opacity-90 transition-transform duration-700 ease-out group-hover:scale-[1.06]';

    slide.appendChild(img);
    host.appendChild(slide);
    return slide;
  });

  let index = 0;
  function show(i) {
    slidesEls.forEach((el, idx) => el.classList.toggle('is-active', idx === i));
  }

  show(0);
  window.setInterval(() => {
    index = (index + 1) % slidesEls.length;
    show(index);
  }, 3500);
}

document.addEventListener('DOMContentLoaded', mountCarousel);
