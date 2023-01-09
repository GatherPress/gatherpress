const GoogleMapEmbed = (props) => {
    const { location, zoom, type, height, className } = props;
    const style = { border: 0, height, width: '100%' };
    const baseUrl = 'https://maps.google.com/maps';

    const params = new URLSearchParams(
        {
            q: location,
            z: zoom || 1,
            t: type,
            output: 'embed',
        }
    );

    const src = baseUrl + '?' + params.toString();
    return (
        <iframe
            src={src}
            style={style}
            className={className}
            title={location}
        ></iframe>
    );
};
export default GoogleMapEmbed;
