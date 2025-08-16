const rowTitles = [
  { name: '', getter: null},
  { name: 'min', getter: record => record.min, isEnable: true},
  { name: 'max', getter: record => record.max, isEnable: true},
  { name: 'avg', getter: record => record.avg, isEnable: true},
  { name: 'avg (P₅-P₉₅)', getter: record => Math.floor((record.p_05 + record.p_95) / 2 * 100) / 100, isEnable: false},
  { name: 'avg (P₂₅-P₇₅)', getter: record => Math.floor((record.p_25 + record.p_75) / 2 * 100) / 100, isEnable: false},
  { name: 'median (P₅₀)', getter: record => record.median, isEnable: true},
  { name: 'P₅', getter: record => record.p_05, isEnable: false},
  { name: 'P₁₀', getter: record => record.p_10, isEnable: false},
  { name: 'P₂₅', getter: record => record.p_25, isEnable: false},
  { name: 'P₇₅', getter: record => record.p_75, isEnable: false},
  { name: 'P₉₀', getter: record => record.p_90, isEnable: false},
  { name: 'P₉₅', getter: record => record.p_95, isEnable: false},
]

let variantsCount = 0;
const getRowCount = () => rowTitles.length;
const getColumnCount = () => variantsCount + 1;

function getQueryHtml(query, name) {
  let rowsHtml = ''

  for (row in rowTitles) {
    if (!rowTitles[row].isEnable) {
      continue;
    }

    rowsHtml += '<tr>'

    for (let col = 0; col < getColumnCount(); col++) {
      const dataCell = query[col][row]

      rowsHtml += `<td`
      if (dataCell.rank !== null) {
        const color = variantToBeautifulColor(dataCell.rank, 5)
        rowsHtml += ` style="background-color:${color}"`
      }

      rowsHtml += `>${dataCell.value}</td>`
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

function renderOptions() {
  let fields = '';
  for (const key in rowTitles) {
    const row = rowTitles[key]
    if (row.name === '') {
      continue;
    }

    fields += `<div class="field" onclick="changeRow(${key})">
      <input type="checkbox" id="${row.name}" name="${row.name}" ${row.isEnable ? 'checked' : ''}/>
      <label for="min">${row.name}</label>
    </div>`
  }

  return `<div class="options">${fields}</div>`
}

function renderStats(data) {
  const tables = [];

  const cell = (value, rank = null) => ({value, rank})
  variantsCount = Object.keys(data).length

  for (const queryKey in data) {
    const query = data[queryKey];

    const firstColumn = [];
    for (const title of rowTitles) {
      firstColumn.push(cell(title.name))
    }

    tables[queryKey] = [firstColumn]

    for (const stat of query) {

      const rowData = [cell(stat.type)]

      for (const rowTitle of rowTitles) {
        if (rowTitle.getter === null) {
          continue;
        }

        rowData.push(cell(rowTitle.getter(stat)))
      }

      tables[queryKey].push(rowData)
    }

    for (let row = 1; row < getRowCount(); row++) {
      let all = []
      for (let col = 1; col < getColumnCount(); col++) {
        all.push(tables[queryKey][col][row].value)
      }

      function objectKeySwapValue(obj) {
        var ret = {};
        for (var key in obj) {
          ret[obj[key]] = key;
        }
        return ret;
      }

      all = all.sort((a, b) => a - b)
      all = objectKeySwapValue(all)

      for (let col = 1; col < getColumnCount(); col++) {
        tables[queryKey][col][row].rank = all[tables[queryKey][col][row].value]
      }
    }
  }
  let html = ''
  for (const queryName in tables) {
    html += getQueryHtml(tables[queryName], queryName)
  }

  return html
}

function changeRow(key) {
  console.log(key)
  rowTitles[key].isEnable = !rowTitles[key].isEnable
  render()
}
function render() {
  document.querySelector('.content').innerHTML = renderOptions() + renderStats(data)
}
render()





