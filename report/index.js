async function loadJSON(url) {
  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return await response.json();
  } catch (error) {
    console.error('Failed to load JSON:', error);
    return null;
  }
}

function variantToBeautifulColor(variant, totalVariants) {
  // Настройки цветового градиента
  const colorStops = [
    { h: 120, s: 90, l: 75 }, // Светло-зелёный
    { h: 60, s: 90, l: 75 },  // Ярко-жёлтый
    { h: 0, s: 90, l: 75 }    // Светло-красный
  ];

  // Нормализация позиции
  const position = totalVariants <= 1 ? 0 : variant / (totalVariants - 1);

  // Выбор сегмента градиента
  const segment = position * (colorStops.length - 1);
  const segIndex = Math.floor(segment);
  const segProgress = segment - segIndex;

  // Интерполяция между цветовыми остановками
  const c1 = colorStops[segIndex];
  const c2 = colorStops[Math.min(segIndex + 1, colorStops.length - 1)];

  const h = c1.h + (c2.h - c1.h) * segProgress;
  const s = c1.s + (c2.s - c1.s) * segProgress;
  const l = c1.l + (c2.l - c1.l) * segProgress;

  // Конвертация HSL в HEX
  return hslToHex(h, s, l);
}

/**
 * Конвертирует HSL в HEX
 * @param {number} h - Оттенок (0..360)
 * @param {number} s - Насыщенность (0..100)
 * @param {number} l - Светлота (0..100)
 * @returns {string} HEX-цвет
 */
function hslToHex(h, s, l) {
  h /= 360;
  s /= 100;
  l /= 100;

  let r, g, b;

  if (s === 0) {
    r = g = b = l;
  } else {
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1/6) return p + (q - p) * 6 * t;
      if (t < 1/2) return q;
      if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
      return p;
    };

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;

    r = hue2rgb(p, q, h + 1/3);
    g = hue2rgb(p, q, h);
    b = hue2rgb(p, q, h - 1/3);
  }

  const toHex = x => {
    const hex = Math.round(x * 255).toString(16);
    return hex.length === 1 ? '0' + hex : hex;
  };

  return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toUpperCase();
}

const data = await loadJSON('data.json');

const tables = [];

const cell = (value, rank = null) => ({value, rank})

for (const queryKey in data) {
  const query = data[queryKey];

  tables[queryKey] = [[cell(''), cell('min'), cell('max'), cell('avg'), cell('median')]]

  for (const stat of query) {
    tables[queryKey].push([
      cell(stat.type),
      cell(stat.min),
      cell(stat.max),
      cell(stat.avg),
      cell(stat.median),
    ])
  }


  for (let row = 1; row < 5; row++) {
    let all = []
    for (let col = 1; col < 6; col++) {
        all.push(tables[queryKey][col][row].value)
    }

    function objectKeySwapValue(obj){
      var ret = {};
      for(var key in obj){
        ret[obj[key]] = key;
      }
      return ret;
    }

    all = all.sort((a, b) => a - b)

    console.log(queryKey, row,all,objectKeySwapValue(all) )

    all = objectKeySwapValue(all) //.reverse()

    for (let col = 1; col < 6; col++) {
      tables[queryKey][col][row].rank = all[tables[queryKey][col][row].value]
    }
  }
}

function getQueryHtml(query, name) {
  let rowsHtml = ''

  for (let row = 0; row < 5; row++) {
    rowsHtml += '<tr>'

    for (let col = 0; col < 6; col++) {
      const dataCell = query[col][row]

      rowsHtml += `<td`
      if (dataCell.rank !== null) {
        const color =  variantToBeautifulColor(dataCell.rank, 5)
        rowsHtml += ` style="background-color:${color}"`
      }

      rowsHtml +=`>${dataCell.value}</td>`
    }
    rowsHtml += '</tr>'

  }

  return `<div class="query">
      <string class="query__name">${name}</string>
      <table>
        ${rowsHtml}
      </table>
    </div>
  `
}

let html = ''
for (const queryName in tables) {
  html += getQueryHtml(tables[queryName], queryName)
}

document.querySelector('.content').innerHTML = html






