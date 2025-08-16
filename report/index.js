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
  { name: 'score', getter: null, isEnable: true},
]
const scoreRowKey = rowTitles.length - 1

const variants = [];
const getRowCount = () => rowTitles.length;
const getColumnCount = () => variants.length + 1;

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

function getQueryHtml(query, name) {
  let rowsHtml = ''

  for (row in rowTitles) {
    if (rowTitles[row].name !== '' && !rowTitles[row].isEnable) {
      continue;
    }

    rowsHtml += '<tr>'

    for (let col = 0; col < getColumnCount(); col++) {
      const dataCell = query[col][row]
      if (!dataCell) {
        // rowsHtml += `<td></td>`
        continue;
      }

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

function renderStats(data) {
  const tables = [];

  const Cell = (value, rank = null) => ({value, rank})
  for (const queryKey in data) {
    if (variants.length === 0) {
      for (const stat of data[queryKey]) {
        variants.push(stat.type)
      }
    }
    break;
  }

  for (const queryKey in data) {
    const query = data[queryKey];

    const firstColumn = [];
    for (const title of rowTitles) {
      firstColumn.push(Cell(title.name))
    }

    tables[queryKey] = [firstColumn]

    for (const stat of query) {
      const rowData = [Cell(stat.type)]

      for (const rowTitle of rowTitles) {
        if (rowTitle.getter === null) {
          continue;
        }

        rowData.push(Cell(rowTitle.getter(stat)))
      }

      tables[queryKey].push(rowData)
    }

    for (let row = 1; row < getRowCount() - 1; row++) {
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
        tables[queryKey][col][row].rank = Number(all[tables[queryKey][col][row].value])
      }
    }

    if (rowTitles[scoreRowKey].isEnable) {
      for (let col = 1; col < getColumnCount(); col++) {
        let totalScore = 0;
        for (let row = 1; row < getRowCount() - 1; row++) {
          if (tables[queryKey][col][row].rank) {
            if (rowTitles[row].isEnable) {
              totalScore += tables[queryKey][col][row].rank
            }
          }
        }

        tables[queryKey][col][getRowCount() - 1] = Cell(totalScore)
      }
    }
  }

  // calculate global total score
  if (rowTitles[scoreRowKey].isEnable) {
    const total = [{0: Cell(''), [scoreRowKey]: Cell('total score')}]

    for (const variant of variants) {
      const column = [Cell(variant)]

      const getVariantKey = variant => {
        for (const key in variants) {
          if (variants[key] === variant) {
            return Number(key)
          }
        }
      }

      let totalScoreByQuery = 0
      for (const query in tables) {
        const variantColumn = tables[query][getVariantKey(variant) + 1]
        totalScoreByQuery += tables[query][getVariantKey(variant) + 1][variantColumn.length - 1].value
      }

      column[scoreRowKey] = Cell(totalScoreByQuery)
      total.push(column)
    }
    tables['total'] = total
    console.log(tables['total'])
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





