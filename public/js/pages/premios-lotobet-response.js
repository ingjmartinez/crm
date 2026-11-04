window.consultarPremiosJson = async function (url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    let data;
    try {
        data = await response.json();
    } catch (_error) {
        const mensaje = response.status === 401 || response.redirected
            ? 'La sesión venció. Inicie sesión nuevamente.'
            : `No se pudo procesar la solicitud (HTTP ${response.status}). Inténtelo nuevamente.`;
        throw new Error(mensaje);
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        throw new Error('El servidor devolvió una respuesta inválida. Inténtelo nuevamente.');
    }
    if (!response.ok || data.error || (data.code !== undefined && !['0', '200'].includes(String(data.code)))) {
        const mensaje = Object.values(data.errors || {}).flat()[0] || data.error || data.message;
        throw new Error(typeof mensaje === 'string' && !/<[^>]+>/.test(mensaje)
            ? mensaje
            : `No se pudo procesar la solicitud (HTTP ${response.status}). Inténtelo nuevamente.`);
    }
    return data;
};
