/**
 * Placeholder cards while the editor preview loads: the grid's real
 * classes and column count, so the block has its final size from the
 * start and nothing jumps when the posts arrive.
 */

/**
 * @param {Object} props
 * @param {number} props.columns 2, 3 or 4.
 * @param {number} props.count   Cards to show (the page size).
 * @return {Element} Skeleton grid.
 */
export default function GridSkeleton( { columns, count } ) {
	return (
		<div
			className={ `wmpgf-grid wmpgf-grid--cols-${ columns }` }
			aria-hidden="true"
		>
			{ Array.from( { length: count }, ( _, index ) => (
				<div key={ index } className="wmpgf-grid__card is-skeleton">
					<div className="wmpgf-grid__thumb" />
					<p className="wmpgf-grid__category">&nbsp;</p>
					<h3 className="wmpgf-grid__title">&nbsp;</h3>
					<div className="wmpgf-grid__excerpt">
						<p>
							&nbsp;
							<br />
							&nbsp;
						</p>
					</div>
				</div>
			) ) }
		</div>
	);
}
