// ===== PRICE SLIDER & EARNINGS =====
const range    = document.getElementById("priceRange");
const display  = document.getElementById("priceDisplay");
const earnings = document.getElementById("earnings");

function updatePrice() {
    const price = Number(range.value);
    display.innerText = "Rs. " + price.toLocaleString() + "/day";
    earnings.innerText = "Rs. " + Math.floor(price * 18).toLocaleString();
}

range.addEventListener("input", updatePrice);
updatePrice();

// ===== COLOR PICKER =====
const colorPicker   = document.getElementById("colorPicker");
const colorTrigger  = document.getElementById("colorTrigger");
const colorPreview  = document.getElementById("colorPreview");
const colorName     = document.getElementById("colorName");
const colorCategory = document.getElementById("colorCategory");
const hexValue      = document.getElementById("hexValue");

colorTrigger.addEventListener("click", () => colorPicker.click());

const cssColors = {
    "#f0f8ff":"AliceBlue","#faebd7":"AntiqueWhite","#00ffff":"Aqua","#7fffd4":"Aquamarine",
    "#f0ffff":"Azure","#f5f5dc":"Beige","#ffe4c4":"Bisque","#000000":"Black","#ffebcd":"BlanchedAlmond",
    "#0000ff":"Blue","#8a2be2":"BlueViolet","#a52a2a":"Brown","#deb887":"BurlyWood","#5f9ea0":"CadetBlue",
    "#7fff00":"Chartreuse","#d2691e":"Chocolate","#ff7f50":"Coral","#6495ed":"CornflowerBlue",
    "#fff8dc":"Cornsilk","#dc143c":"Crimson","#008b8b":"DarkCyan","#b8860b":"DarkGoldenRod",
    "#a9a9a9":"DarkGray","#006400":"DarkGreen","#bdb76b":"DarkKhaki","#8b008b":"DarkMagenta",
    "#556b2f":"DarkOliveGreen","#ff8c00":"DarkOrange","#9932cc":"DarkOrchid","#8b0000":"DarkRed",
    "#e9967a":"DarkSalmon","#8fbc8f":"DarkSeaGreen","#483d8b":"DarkSlateBlue","#2f4f4f":"DarkSlateGray",
    "#00ced1":"DarkTurquoise","#9400d3":"DarkViolet","#ff1493":"DeepPink","#00bfff":"DeepSkyBlue",
    "#696969":"DimGray","#1e90ff":"DodgerBlue","#b22222":"FireBrick","#228b22":"ForestGreen",
    "#ff00ff":"Fuchsia","#dcdcdc":"Gainsboro","#ffd700":"Gold","#daa520":"GoldenRod",
    "#808080":"Gray","#008000":"Green","#adff2f":"GreenYellow","#ff69b4":"HotPink",
    "#cd5c5c":"IndianRed","#4b0082":"Indigo","#f0e68c":"Khaki","#e6e6fa":"Lavender",
    "#7cfc00":"LawnGreen","#add8e6":"LightBlue","#f08080":"LightCoral","#90ee90":"LightGreen",
    "#ffb6c1":"LightPink","#ffa07a":"LightSalmon","#20b2aa":"LightSeaGreen","#87cefa":"LightSkyBlue",
    "#778899":"LightSlateGray","#b0c4de":"LightSteelBlue","#00ff00":"Lime","#32cd32":"LimeGreen",
    "#800000":"Maroon","#66cdaa":"MediumAquaMarine","#0000cd":"MediumBlue","#ba55d3":"MediumOrchid",
    "#9370db":"MediumPurple","#3cb371":"MediumSeaGreen","#7b68ee":"MediumSlateBlue",
    "#00fa9a":"MediumSpringGreen","#48d1cc":"MediumTurquoise","#c71585":"MediumVioletRed",
    "#191970":"MidnightBlue","#000080":"Navy","#808000":"Olive","#6b8e23":"OliveDrab",
    "#ffa500":"Orange","#ff4500":"OrangeRed","#da70d6":"Orchid","#98fb98":"PaleGreen",
    "#afeeee":"PaleTurquoise","#db7093":"PaleVioletRed","#ffc0cb":"Pink","#dda0dd":"Plum",
    "#b0e0e6":"PowderBlue","#800080":"Purple","#ff0000":"Red","#bc8f8f":"RosyBrown",
    "#4169e1":"RoyalBlue","#8b4513":"SaddleBrown","#fa8072":"Salmon","#f4a460":"SandyBrown",
    "#2e8b57":"SeaGreen","#a0522d":"Sienna","#c0c0c0":"Silver","#87ceeb":"SkyBlue",
    "#6a5acd":"SlateBlue","#708090":"SlateGray","#00ff7f":"SpringGreen","#4682b4":"SteelBlue",
    "#d2b48c":"Tan","#008080":"Teal","#d8bfd8":"Thistle","#ff6347":"Tomato",
    "#40e0d0":"Turquoise","#ee82ee":"Violet","#f5deb3":"Wheat","#ffffff":"White",
    "#f5f5f5":"WhiteSmoke","#ffff00":"Yellow","#9acd32":"YellowGreen",
    "#e03030":"Racing Red","#c82020":"Deep Red"
};

function getNearestColor(hex) {
    hex = hex.toLowerCase();
    if (cssColors[hex]) return cssColors[hex];
    const r1 = parseInt(hex.substr(1,2), 16);
    const g1 = parseInt(hex.substr(3,2), 16);
    const b1 = parseInt(hex.substr(5,2), 16);
    let minDist = Infinity, closest = "Custom";
    for (const [cHex, name] of Object.entries(cssColors)) {
        const r2 = parseInt(cHex.substr(1,2), 16);
        const g2 = parseInt(cHex.substr(3,2), 16);
        const b2 = parseInt(cHex.substr(5,2), 16);
        const dist = Math.sqrt((r1-r2)**2 + (g1-g2)**2 + (b1-b2)**2);
        if (dist < minDist) { minDist = dist; closest = name; }
    }
    return closest;
}

function getColorCategory(hex) {
    const r = parseInt(hex.substr(1,2), 16);
    const g = parseInt(hex.substr(3,2), 16);
    const b = parseInt(hex.substr(5,2), 16);
    const max = Math.max(r,g,b), min = Math.min(r,g,b), delta = max - min;
    if (delta === 0) return "Neutral";
    let hue = 0;
    if (max === r)      hue = ((g-b)/delta) % 6;
    else if (max === g) hue = ((b-r)/delta) + 2;
    else                hue = ((r-g)/delta) + 4;
    hue = Math.round(hue * 60);
    if (hue < 0) hue += 360;
    if (hue < 15 || hue >= 345) return "Red";
    if (hue < 45)  return "Orange";
    if (hue < 70)  return "Yellow";
    if (hue < 150) return "Green";
    if (hue < 210) return "Cyan";
    if (hue < 270) return "Blue / Purple";
    if (hue < 330) return "Pink / Magenta";
    return "Other";
}

function updateColorUI(hex) {
    colorPreview.style.background = hex;
    hexValue.innerText      = hex;
    colorName.innerText     = getNearestColor(hex);
    colorCategory.innerText = getColorCategory(hex);
}

colorPicker.addEventListener("input", () => updateColorUI(colorPicker.value));
updateColorUI(colorPicker.value);

// ===== COLOR PRESETS =====
const carPresets = [
    // Reds & Pinks
    { hex: "#e03030", name: "Racing Red" },
    { hex: "#c82020", name: "Deep Red" },
    { hex: "#8b0000", name: "Dark Red" },
    { hex: "#ff6347", name: "Tomato" },
    // Whites & Silvers
    { hex: "#ffffff", name: "White" },
    { hex: "#f5f5f5", name: "Pearl White" },
    { hex: "#c0c0c0", name: "Silver" },
    { hex: "#a9a9a9", name: "Dark Gray" },
    // Blacks & Grays
    { hex: "#1a1a1a", name: "Midnight Black" },
    { hex: "#2f2f2f", name: "Graphite" },
    { hex: "#708090", name: "Slate Gray" },
    { hex: "#808080", name: "Gray" },
    // Blues
    { hex: "#003366", name: "Navy Blue" },
    { hex: "#1e3a5f", name: "Deep Blue" },
    { hex: "#4169e1", name: "Royal Blue" },
    { hex: "#87ceeb", name: "Sky Blue" },
    // Greens
    { hex: "#1a3c1a", name: "Forest Green" },
    { hex: "#2e8b57", name: "Sea Green" },
    { hex: "#006400", name: "Dark Green" },
    { hex: "#556b2f", name: "Olive Green" },
    // Browns & Golds
    { hex: "#3d1f00", name: "Dark Brown" },
    { hex: "#8b4513", name: "Saddle Brown" },
    { hex: "#d4a017", name: "Gold" },
    { hex: "#b8860b", name: "Dark Gold" },
    // Oranges & Yellows
    { hex: "#ff8c00", name: "Dark Orange" },
    { hex: "#ff4500", name: "Orange Red" },
    { hex: "#ffcc00", name: "Yellow" },
    { hex: "#f5c518", name: "Amber" },
    // Purples
    { hex: "#4b0082", name: "Indigo" },
    { hex: "#6a0dad", name: "Purple" },
    { hex: "#9370db", name: "Medium Purple" },
    { hex: "#483d8b", name: "Dark Slate Blue" },
];

const presetGrid = document.getElementById("colorPresetGrid");

carPresets.forEach(({ hex, name }) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "lc-preset-swatch";
    btn.style.background = hex;
    btn.title = name;

    btn.addEventListener("click", () => {
        colorPicker.value = hex;
        updateColorUI(hex);
        setActivePreset(hex);
    });

    presetGrid.appendChild(btn);
});

function setActivePreset(hex) {
    document.querySelectorAll(".lc-preset-swatch").forEach(btn => {
        btn.classList.toggle("active", btn.style.background === hexToRgb(hex) || btn.title === getNearestColor(hex) && btn.style.background === hex);
    });
    // simpler approach: match by title
    document.querySelectorAll(".lc-preset-swatch").forEach(btn => {
        const btnHex = rgbToHex(btn.style.background);
        btn.classList.toggle("active", btnHex.toLowerCase() === hex.toLowerCase());
    });
}

function hexToRgb(hex) {
    const r = parseInt(hex.substr(1,2),16);
    const g = parseInt(hex.substr(3,2),16);
    const b = parseInt(hex.substr(5,2),16);
    return `rgb(${r}, ${g}, ${b})`;
}

function rgbToHex(rgb) {
    const m = rgb.match(/\d+/g);
    if (!m) return "#000000";
    return "#" + m.slice(0,3).map(n => parseInt(n).toString(16).padStart(2,"0")).join("");
}

// Also update active state when the custom picker changes
colorPicker.addEventListener("input", () => {
    updateColorUI(colorPicker.value);
    setActivePreset(colorPicker.value);
});

// Set initial active preset
setActivePreset(colorPicker.value);

// ===== IMAGE DROPZONE & PREVIEW =====
const dropzone     = document.getElementById("dropzone");
const vehicleImage = document.getElementById("vehicleImage");
const imagePreview = document.getElementById("imagePreview");
const previewWrap  = document.getElementById("previewWrap");
const removeBtn    = document.getElementById("removeImage");

function showPreview(file) {
    if (!file || !file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = e => {
        imagePreview.src   = e.target.result;
        previewWrap.style.display = "flex";   // show preview + remove button
        dropzone.style.display    = "none";   // hide dropzone
    };
    reader.readAsDataURL(file);
}

function clearPreview() {
    vehicleImage.value        = "";           // clear the file input
    imagePreview.src          = "#";
    previewWrap.style.display = "none";       // hide preview + remove button
    dropzone.style.display    = "";           // restore dropzone
}

vehicleImage.addEventListener("change", () => showPreview(vehicleImage.files[0]));
removeBtn.addEventListener("click", clearPreview);

dropzone.addEventListener("dragover",  e => { e.preventDefault(); dropzone.classList.add("drag-over"); });
dropzone.addEventListener("dragleave", ()  => dropzone.classList.remove("drag-over"));
dropzone.addEventListener("drop", e => {
    e.preventDefault();
    dropzone.classList.remove("drag-over");
    showPreview(e.dataTransfer.files[0]);
});