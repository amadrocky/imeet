import { registerVueControllerComponents } from '@symfony/ux-vue';
import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/css/app.scss';

registerVueControllerComponents(require.context('./vue/controllers', true, /\.vue$/));

import LocomotiveScroll from 'locomotive-scroll';

window.onload = function () {
    const scroll = new LocomotiveScroll({ 
        el: document.querySelector('[data-scroll-container]'),
         smooth: true, 
         smoothMobile: false,
         mobile: { smooth: false }, 
         tablet: { smooth: false } 
    });
    
    function updateScroll() {
        scroll.destroy(); 
        scroll.init(); 
    }; 
    
    updateScroll();
    
    window.onresize = updateScroll; 
}