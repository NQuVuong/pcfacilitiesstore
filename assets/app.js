// assets/app.js
import './styles/app.css';

// JS khác 

// Lucide icons (không CDN)
import lucide from 'lucide';
document.addEventListener('DOMContentLoaded', () => {
  try { lucide.createIcons(); } catch (e) {}
});
