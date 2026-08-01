// modules/add_form/lldp_parser.js

function detectLLDPVendor(output) {
    const lines = output.split('\n');
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line.startsWith('Device ID') && line.includes('Local Intf')) return 'cisco';
        if (line.startsWith('Local Intf') && line.includes('Neighbor Dev')) return 'huawei';
        if (line.startsWith('Port') && line.includes('Device ID') && line.includes('Port ID')) {
            if (i + 1 < lines.length && /^-{5,}/.test(lines[i + 1].trim())) {
                return 'eltex';
            }
        }
    }
    return null;
}

function isValidInterface(name) {
    if (!name) return false;
    if (/^[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}\.[0-9a-fA-F]{4}$/.test(name) ||
        /^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/.test(name) ||
        /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(name)) {
        return false;
    }
    const upper = name.toUpperCase();
    const prefixes = [
        'GI', 'FA', 'TE', 'TW', 'FO', 'HU', 'TWE', 'GIG', 'XGE', 'XGIG',
        '10GE', '25GE', '40GE', '50GE', '100GE', '200GE', '400GE',
        'GE', 'ETH', 'FASTETHERNET', 'GIGABITETHERNET', 'TENGIGABITETHERNET',
        'SERIAL', 'LOOPBACK', 'VLAN', 'PORT-CHANNEL', 'PO', 'TUNNEL',
        'MGMT', 'MANAGEMENT'
    ];
    for (const prefix of prefixes) {
        if (upper.startsWith(prefix)) {
            const rest = name.substring(prefix.length);
            if (/^[\d\/\.\-]+$/.test(rest)) return true;
        }
    }
    return false;
}

function formatEltexDeviceId(id) {
    if (!id) return id;
    if (/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(id)) return id;
    let hex = id.replace(/[^0-9a-fA-F]/g, '');
    if (hex.length === 12) {
        hex = hex.toLowerCase();
        return hex.substr(0,4) + '-' + hex.substr(4,4) + '-' + hex.substr(8,4);
    }
    return id;
}

/**
 * Вырезает из вывода только ту часть, которая содержит таблицу LLDP.
 * Возвращает массив строк, которые гарантированно относятся к данным.
 */
function extractTableLines(output, vendor) {
    const allLines = output.split('\n').map(l => l.trimEnd());
    const tableLines = [];
    let inTable = false;

    if (vendor === 'cisco') {
        for (const line of allLines) {
            if (line.startsWith('Device ID')) { inTable = true; continue; }
            if (line.startsWith('Total entries')) break;
            if (inTable) tableLines.push(line);
        }
    } else if (vendor === 'huawei') {
        for (const line of allLines) {
            if (line.startsWith('Local Intf')) { inTable = true; continue; }
            if (inTable && line.trim() === '') continue;
            if (inTable) tableLines.push(line);
        }
    } else if (vendor === 'eltex') {
        // Ищем начало таблицы: строка заголовка "Port ... Device ID ... Port ID"
        let startIdx = -1;
        for (let i = 0; i < allLines.length; i++) {
            const line = allLines[i].trim();
            if (line.startsWith('Port') && line.includes('Device ID') && line.includes('Port ID')) {
                // Убедимся, что следующая строка - разделитель из дефисов
                if (i + 1 < allLines.length && /^-{5,}/.test(allLines[i + 1].trim())) {
                    startIdx = i + 2; // данные начинаются через строку после дефисов
                    break;
                }
            }
        }
        if (startIdx !== -1) {
            for (let i = startIdx; i < allLines.length; i++) {
                const line = allLines[i].trim();
                // Останавливаемся, если строка начинается с букв и содержит '#', '>' и т.п. (приглашение командной строки)
                // или если это строка-разделитель типа "──────" или "────────────────"
                if (/^[A-Za-z0-9_-]+[#>]\s*$/.test(line) || /^[─━═]+$/.test(line)) break;
                // Пропускаем строку "TP - Two Ports MAC Relay; ..." (начинается с "TP")
                if (line.startsWith('TP ')) continue;
                // Если строка пустая, пропускаем
                if (line === '') continue;
                tableLines.push(allLines[i]); // сохраняем в оригинальном виде (с пробелами)
            }
        }
    }
    return tableLines;
}

function parseLLDPOutput(output, vendor) {
    const neighbors = [];
    // Получаем только строки таблицы
    const lines = extractTableLines(output, vendor);

    if (vendor === 'cisco') {
        for (const line of lines) {
            if (line.trim() === '') continue;
            const parts = line.split(/\s{2,}/);
            if (parts.length >= 5) {
                const portId = parts[4].trim();
                if (isValidInterface(portId)) {
                    neighbors.push({
                        local_interface: parts[1].trim(),
                        neighbor_hostname: parts[0].trim(),
                        neighbor_interface: portId
                    });
                }
            }
        }
    } else if (vendor === 'huawei') {
    for (const line of lines) {
        if (line.trim() === '') continue;
        // Разбиваем по любым пробельным символам, чтобы получить все токены
        const tokens = line.trim().split(/\s+/);
        if (tokens.length < 3) continue;
        // Ищем самый правый токен, похожий на интерфейс
        let ifaceIdx = -1;
        for (let i = tokens.length - 1; i >= 1; i--) {
            if (isValidInterface(tokens[i])) {
                ifaceIdx = i;
                break;
            }
        }
        // Если не нашли валидный интерфейс, пропускаем строку
        if (ifaceIdx === -1) continue;

        const localIntf = tokens[0];
        // Имя соседа – все токены между первым и найденным интерфейсом
        const neighborDev = tokens.slice(1, ifaceIdx).join(' ').trim();
        const neighborIntf = tokens[ifaceIdx];

        if (neighborDev !== '-' && neighborDev !== '') {
            neighbors.push({
                local_interface: localIntf,
                neighbor_hostname: neighborDev,
                neighbor_interface: neighborIntf
            });
        }
    }
} else if (vendor === 'eltex') {
        let currentEntry = null;
        for (const line of lines) {
            if (line.trim() === '') continue;
            // Строка начинается с интерфейса (после возможных пробелов)
            if (/^[a-z]+\d[\d\/]*\s+/i.test(line.trimStart())) {
                if (currentEntry && currentEntry.deviceId) {
                    neighbors.push({
                        local_interface: currentEntry.port,
                        neighbor_hostname: formatEltexDeviceId(currentEntry.deviceId),
                        neighbor_interface: currentEntry.portId || ''
                    });
                }
                const parts = line.trimStart().split(/\s{2,}/);
                if (parts.length >= 3) {
                    currentEntry = {
                        port: parts[0].trim(),
                        deviceId: parts[1].trim(),
                        portId: parts[2].trim()
                    };
                } else {
                    currentEntry = null;
                }
            } else if (currentEntry) {
                // Продолжение portId
                currentEntry.portId += line.trim();
            }
        }
        if (currentEntry && currentEntry.deviceId) {
            neighbors.push({
                local_interface: currentEntry.port,
                neighbor_hostname: formatEltexDeviceId(currentEntry.deviceId),
                neighbor_interface: currentEntry.portId || ''
            });
        }
    }

    // Финальный фильтр по валидности порта соседа
    return neighbors.filter(n => isValidInterface(n.neighbor_interface));
}