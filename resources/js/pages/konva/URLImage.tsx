import useImage from "use-image";
import { URLImageProps } from "./types";
import { Image } from "react-konva";

const URLImage = ({ image }: URLImageProps) => {
    const [img] = useImage(image.src);
    return (
        <Image
            image={img || undefined}
            x={image.x}
            y={image.y}
            offsetX={img ? img.width / 2 : 0}
            offsetY={img ? img.height / 2 : 0}
            draggable
            onMouseEnter={() => {
                document.body.style.cursor = 'pointer';
            }}
            onMouseLeave={() => {
                document.body.style.cursor = 'default';
            }}
        />
    );
};
export default URLImage;
