<?php /** The side companion on the home page: a hiker on a trail, or the road-trip car. Driven by main.js. */ ?>
<div class="trip" id="trip" aria-hidden="true">
  <svg viewBox="0 0 170 900" preserveAspectRatio="xMidYMid meet">
    <defs><path id="roadPath" d="M88 30 C 30 120, 150 190, 88 290 S 30 460, 88 560 S 150 720, 88 870"/></defs>
    <g class="trees">
      <g transform="translate(24 100)"><rect class="trunk" x="-2" y="10" width="4" height="8"/><path class="tree" d="M0 -12 L-9 10 L9 10 Z"/></g>
      <g transform="translate(150 240)"><rect class="trunk" x="-2" y="10" width="4" height="8"/><path class="tree" d="M0 -12 L-9 10 L9 10 Z"/></g>
      <g transform="translate(20 380)"><rect class="trunk" x="-2" y="8" width="4" height="7"/><path class="tree" d="M0 -10 L-7 8 L7 8 Z"/></g>
      <g transform="translate(152 520)"><rect class="trunk" x="-2" y="10" width="4" height="8"/><path class="tree" d="M0 -12 L-9 10 L9 10 Z"/></g>
      <g transform="translate(26 650)"><rect class="trunk" x="-2" y="10" width="4" height="8"/><path class="tree" d="M0 -12 L-9 10 L9 10 Z"/></g>
      <g transform="translate(148 800)"><rect class="trunk" x="-2" y="8" width="4" height="7"/><path class="tree" d="M0 -10 L-7 8 L7 8 Z"/></g>
    </g>
    <use href="#roadPath" class="edge"/><use href="#roadPath" class="road"/><use href="#roadPath" class="mid" id="roadMid"/>
    <g id="signs"></g>
    <g transform="translate(88 872)"><line x1="0" y1="0" x2="0" y2="-26" stroke="#3B4A45" stroke-width="2"/><path class="flag" d="M0 -26 L22 -19 L0 -12 Z"/></g>
    <g class="car" id="car">
      <rect x="-9" y="-16" width="18" height="32" rx="5" fill="#E85305"/><rect x="-7" y="-9" width="14" height="9" rx="2" fill="#7FD3EE"/><rect x="-7" y="3" width="14" height="7" rx="2" fill="#7FD3EE"/>
      <rect x="-11" y="-12" width="3" height="7" rx="1" fill="#131313"/><rect x="8" y="-12" width="3" height="7" rx="1" fill="#131313"/><rect x="-11" y="6" width="3" height="7" rx="1" fill="#131313"/><rect x="8" y="6" width="3" height="7" rx="1" fill="#131313"/>
      <circle cx="-5" cy="-15" r="1.6" fill="#FFF9C4"/><circle cx="5" cy="-15" r="1.6" fill="#FFF9C4"/>
    </g>
    <g class="hiker" id="hiker">
      <rect class="pack" x="4" y="-9" width="7" height="12" rx="2"/>
      <path class="leg" id="legA" d="M0 4 L-5 14 L-7 20"/><path class="leg" id="legB" d="M0 4 L5 13 L8 19"/>
      <rect class="body" x="-5" y="-9" width="10" height="15" rx="4"/><circle class="skin" cx="0" cy="-14" r="4.2"/>
      <path class="arm" id="armA" d="M-4 -5 L-10 3"/><path class="stick" d="M-10 3 L-12 20"/><path class="arm" id="armB" d="M4 -5 L9 2"/>
    </g>
  </svg>
</div>
